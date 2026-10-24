<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethodType;
use App\Enums\RoleName;
use App\Enums\WorkOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\WorkOrder;
use App\Notifications\InvoiceDueNotification;
use App\Services\Cash\CashService;
use App\Services\Invoicing\InvoiceService;
use App\Services\Payments\InvoiceDueNotifier;
use App\Services\Payments\PaymentService;
use App\Services\Tenancy\ProvisionedTenant;
use App\Support\Terbilang;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The branch's default "Tunai" method (into the cash drawer).
 */
function cashMethod(ProvisionedTenant $shop): PaymentMethod
{
    return inTenant($shop->tenant, $shop->branch, fn () => PaymentMethod::query()->with('account')->where('type', 'cash')->firstOrFail());
}

/**
 * A method into a bank account, e.g. QRIS or a credit card with a surcharge.
 */
function bankMethod(ProvisionedTenant $shop, PaymentMethodType $type = PaymentMethodType::Qris, string $surcharge = '0.00', bool $needsReference = false): PaymentMethod
{
    $bank = accountIn($shop, 'bank');

    return inTenant($shop->tenant, $shop->branch, fn () => PaymentMethod::factory()->create([
        'type' => $type, 'cash_account_id' => $bank->id, 'surcharge_percent' => $surcharge, 'needs_reference' => $needsReference,
    ])->load('account'));
}

/**
 * An unpaid invoice of Rp 200.000 for a completed work order.
 *
 * @return array{Invoice, WorkOrder}
 */
function unpaidInvoice(ProvisionedTenant $shop): array
{
    $workOrder = completedWorkOrder($shop);
    $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));

    return [$invoice, $workOrder];
}

function balanceOf(ProvisionedTenant $shop, CashAccount $account): int
{
    return inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->balance($account));
}

function jakartaToday(): string
{
    return now('Asia/Jakarta')->toDateString();
}

describe('checkout', function () {
    it('takes several methods at once, each into its own cash account, on one kwitansi', function () {
        $shop = provisionTenant();
        [$invoice, $workOrder] = unpaidInvoice($shop);
        $cash = cashMethod($shop);
        $qris = bankMethod($shop);

        $receipt = payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [
            ['method' => $cash, 'amount' => 50_000, 'cash_tendered' => 100_000],
            ['method' => $qris, 'amount' => 150_000],
        ]));

        expect($receipt->number)->toStartWith($shop->branch->code.'/KW/')
            ->and($receipt->amount)->toBe(200_000)
            ->and($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid)
            ->and($workOrder->fresh()?->status)->toBe(WorkOrderStatus::Closed)
            // Only the amount goes into the drawer; the rest of the Rp 100.000 was change.
            ->and(balanceOf($shop, $cash->account))->toBe(50_000)
            ->and(balanceOf($shop, $qris->account))->toBe(150_000);
    });

    it('lets the rest be paid later with a different method, asking for a due date until then', function () {
        $shop = provisionTenant();
        [$invoice] = unpaidInvoice($shop);
        $cash = cashMethod($shop);
        $transfer = bankMethod($shop, PaymentMethodType::Transfer);

        expect(fn () => payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => $cash, 'amount' => 80_000]])))
            ->toThrow(BusinessRuleViolation::class);

        $due = now('Asia/Jakarta')->addDays(7)->toDateString();
        payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => $cash, 'amount' => 80_000]], $due));

        expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Partial)
            ->and($invoice->fresh()?->due_date?->toDateString())->toBe($due);

        payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => $transfer, 'amount' => 120_000]]));

        expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => PaymentReceipt::query()->count()))->toBe(2);
    });

    it('adds the card surcharge on top without changing the invoice', function () {
        $shop = provisionTenant();
        [$invoice] = unpaidInvoice($shop);
        $card = bankMethod($shop, PaymentMethodType::CreditCard, '3.00', needsReference: true);

        expect(fn () => payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => $card, 'amount' => 200_000]])))
            ->toThrow(BusinessRuleViolation::class);

        $receipt = payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => $card, 'amount' => 200_000, 'reference' => 'APR123']]));

        expect($receipt->surcharge_total)->toBe(6_000)
            ->and($invoice->fresh()?->total)->toBe(200_000)
            ->and($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid)
            ->and(balanceOf($shop, $card->account))->toBe(206_000);
    });

    it('refuses to take more than is owed, and cash handed over that does not cover the amount', function () {
        $shop = provisionTenant();
        [$invoice] = unpaidInvoice($shop);
        $cash = cashMethod($shop);

        expect(fn () => payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => $cash, 'amount' => 200_001]])))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => $cash, 'amount' => 200_000, 'cash_tendered' => 150_000]])))->toThrow(BusinessRuleViolation::class)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => CashMovement::query()->count()))->toBe(0);
    });
});

describe('corrections', function () {
    it('cancels one line, keeps it on record, takes the money out again and reopens the work order', function () {
        $shop = provisionTenant();
        [$invoice, $workOrder] = unpaidInvoice($shop);
        $cash = cashMethod($shop);
        $qris = bankMethod($shop);
        $receipt = payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [
            ['method' => $cash, 'amount' => 50_000],
            ['method' => $qris, 'amount' => 150_000],
        ]));
        $qrisLine = inTenant($shop->tenant, $shop->branch, fn () => Payment::query()->where('payment_method_id', $qris->id)->sole());

        payments($shop, fn (PaymentService $service) => $service->voidPayment($qrisLine, 'QRIS gagal masuk'));

        expect($qrisLine->isVoided())->toBeTrue()
            ->and($qrisLine->void_reason)->toBe('QRIS gagal masuk')
            ->and(inTenant($shop->tenant, $shop->branch, fn () => Payment::query()->count()))->toBe(2)
            ->and(balanceOf($shop, $qris->account))->toBe(0)
            ->and(balanceOf($shop, $cash->account))->toBe(50_000)
            ->and($invoice->fresh()?->amount_paid)->toBe(50_000)
            ->and($invoice->fresh()?->status)->toBe(InvoiceStatus::Partial)
            ->and($workOrder->fresh()?->status)->toBe(WorkOrderStatus::Invoiced)
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'payment_voided')->value('reason')))->toBe('QRIS gagal masuk')
            ->and(fn () => payments($shop, fn (PaymentService $service) => $service->voidPayment($qrisLine, 'Lagi')))->toThrow(BusinessRuleViolation::class);

        payments($shop, fn (PaymentService $service) => $service->voidReceipt($receipt, 'Salah invoice'));

        expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Unpaid)
            ->and(balanceOf($shop, $cash->account))->toBe(0);
    });

    it('lets the invoice be cancelled once all its payments are cancelled', function () {
        $shop = provisionTenant();
        [$invoice] = unpaidInvoice($shop);
        $receipt = payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => cashMethod($shop), 'amount' => 200_000]]));

        expect(fn () => invoices($shop, fn (InvoiceService $service) => $service->void($invoice->fresh(), 'x')))->toThrow(BusinessRuleViolation::class);

        payments($shop, fn (PaymentService $service) => $service->voidReceipt($receipt, 'Salah'));
        invoices($shop, fn (InvoiceService $service) => $service->void($invoice->fresh(), 'Salah'));

        expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Void);
    });
});

describe('HTTP', function () {
    it('lets Front Desk take a split payment; Finance alone may cancel', function () {
        $shop = provisionTenant();
        [$invoice] = unpaidInvoice($shop);
        $qris = bankMethod($shop);
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);

        $this->actingAs($frontDesk)->post(route('invoices.payments.store', $invoice), [
            'paid_on' => jakartaToday(),
            'due_date' => now('Asia/Jakarta')->addDays(3)->toDateString(),
            'lines' => [
                ['payment_method_id' => cashMethod($shop)->id, 'amount' => 20_000, 'cash_tendered' => 50_000],
                ['payment_method_id' => $qris->id, 'amount' => 100_000],
            ],
        ])->assertSessionHasNoErrors();

        $line = inTenant($shop->tenant, $shop->branch, fn () => Payment::query()->where('payment_method_id', $qris->id)->sole());

        $this->actingAs($frontDesk)->post(route('payments.void', $line), ['reason' => 'x'])->assertForbidden();
        $this->actingAs(userWithRole($shop, RoleName::Finance))->post(route('payments.void', $line), ['reason' => 'Dobel'])->assertSessionHasNoErrors();

        $this->actingAs($frontDesk)->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.status', 'partial')
                ->where('invoice.outstanding', 180_000)
                ->where('receipts.0.lines.1.voided', true)
                ->where('receipts.0.lines.1.void_reason', 'Dobel')
                ->where('can.voidPayment', false));
    });

    it('refuses a method of another branch and a future payment date', function () {
        $shop = provisionTenant();
        [$invoice] = unpaidInvoice($shop);
        $east = addBranch($shop);
        $foreign = inTenant($shop->tenant, $east, fn () => PaymentMethod::factory()->create());

        $this->actingAs($shop->owner)->post(route('invoices.payments.store', $invoice), [
            'paid_on' => jakartaToday(), 'lines' => [['payment_method_id' => $foreign->id, 'amount' => 200_000]],
        ])->assertSessionHasErrors('lines.0.payment_method_id');

        $this->actingAs($shop->owner)->post(route('invoices.payments.store', $invoice), [
            'paid_on' => now('Asia/Jakarta')->addDay()->toDateString(), 'lines' => [['payment_method_id' => cashMethod($shop)->id, 'amount' => 200_000]],
        ])->assertSessionHasErrors('paid_on');
    });

    it('prints the kwitansi with the amount in words, marking cancelled lines', function () {
        $shop = provisionTenant();
        [$invoice] = unpaidInvoice($shop);
        $receipt = payments($shop, fn (PaymentService $service) => $service->checkout($invoice, jakartaToday(), [['method' => cashMethod($shop), 'amount' => 200_000]]));

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('receipts.print', $receipt))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Receipt')
                ->where('receipt.amount_words', 'dua ratus ribu rupiah')
                ->where('receipt.lines.0.voided', false));
        $this->actingAs(provisionTenant('Bengkel Lain')->owner)->get(route('receipts.print', $receipt))->assertNotFound();
    });

    it('lists customer debt, nearest due date first, for staff who take payments', function () {
        $shop = provisionTenant();
        [$late] = unpaidInvoice($shop);
        $late->forceFill(['due_date' => now('Asia/Jakarta')->subDay()->toDateString()])->saveQuietly();
        unpaidInvoice($shop);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('customer-debts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Debts')
                ->has('debts.data', 2)
                ->where('debts.data.0.number', $late->number)
                ->where('debts.data.0.is_overdue', true)
                ->where('outstandingLabel', 'Rp 400.000')
                ->where('overdueLabel', 'Rp 200.000'));
        $this->actingAs(userWithRole($shop, RoleName::Warehouse))->get(route('customer-debts.index'))->assertForbidden();
    });
});

it('reminds staff who take payments once before the due date and once when overdue', function () {
    Notification::fake();
    $shop = provisionTenant();
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);
    $warehouse = userWithRole($shop, RoleName::Warehouse);
    [$soon] = unpaidInvoice($shop);
    [$late] = unpaidInvoice($shop);
    $soon->forceFill(['due_date' => now('Asia/Jakarta')->addDays(2)->toDateString()])->saveQuietly();
    $late->forceFill(['due_date' => now('Asia/Jakarta')->subDay()->toDateString()])->saveQuietly();

    expect(app(InvoiceDueNotifier::class)->run())->toBe(2)
        ->and(app(InvoiceDueNotifier::class)->run())->toBe(0);

    Notification::assertSentTo($frontDesk, InvoiceDueNotification::class, fn ($notification, $channels, $user) => $notification->toArray($user)['kind'] === InvoiceDueNotification::OVERDUE
        && $notification->toArray($user)['invoice_id'] === $late->id);
    Notification::assertNotSentTo($warehouse, InvoiceDueNotification::class);
});

it('writes Rupiah in Indonesian words', function (int $amount, string $words) {
    expect(Terbilang::rupiah($amount))->toBe($words);
})->with([
    [0, 'nol rupiah'],
    [11, 'sebelas rupiah'],
    [115, 'seratus lima belas rupiah'],
    [1_000, 'seribu rupiah'],
    [1_250_000, 'satu juta dua ratus lima puluh ribu rupiah'],
    [21_011_500, 'dua puluh satu juta sebelas ribu lima ratus rupiah'],
    [2_000_000_000, 'dua miliar rupiah'],
]);

it('keeps receipts and payments isolated per tenant', function () {
    $make = function (ProvisionedTenant $shop): PaymentReceipt {
        $invoice = app(InvoiceService::class)->createWalkIn(null, invoiceInput(), [['type' => 'other', 'description' => 'x', 'qty' => 100, 'unit_price' => 10_000]]);

        return app(PaymentService::class)->checkout($invoice, jakartaToday(), [['method' => cashMethod($shop), 'amount' => 10_000]]);
    };

    assertTenantIsolated(PaymentReceipt::class, $make);
    assertTenantIsolated(Payment::class, fn (ProvisionedTenant $shop) => $make($shop)->payments()->firstOrFail());
});
