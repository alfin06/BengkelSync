<?php

use App\Enums\CashMovementType;
use App\Enums\PurchaseStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Part;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Notifications\PurchaseDueNotification;
use App\Services\Cash\CashService;
use App\Services\Inventory\StockService;
use App\Services\Purchasing\PurchaseDueNotifier;
use App\Services\Purchasing\PurchaseService;
use App\Services\Purchasing\SupplierPaymentService;
use App\Services\Tenancy\ProvisionedTenant;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function supplierIn(ProvisionedTenant $shop): Supplier
{
    return inTenant($shop->tenant, $shop->branch, fn () => Supplier::factory()->create());
}

/**
 * The shop's default cash drawer, with money in it.
 */
function fundedDrawer(ProvisionedTenant $shop, int $amount = 5_000_000): CashAccount
{
    return inTenant($shop->tenant, $shop->branch, function () use ($amount) {
        $drawer = CashAccount::query()->where('type', 'cash_drawer')->firstOrFail();
        app(CashService::class)->record($drawer, CashMovementType::TransferIn, $amount);

        return $drawer;
    });
}

/**
 * A purchase of 10 pcs at Rp 45.000 (total Rp 450.000), unpaid.
 */
function purchaseIn(ProvisionedTenant $shop, ?Part $part = null, ?string $dueDate = null): Purchase
{
    $part ??= partIn($shop, ['cost_price' => 0]);
    $supplier = supplierIn($shop);

    return inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->create(
        $supplier,
        ['purchase_date' => now('Asia/Jakarta')->toDateString(), 'due_date' => $dueDate],
        [['part_id' => $part->id, 'qty' => 1000, 'unit_cost' => 45_000]],
    ));
}

function available(ProvisionedTenant $shop, Part $part): int
{
    return inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validPurchase(Supplier $supplier, Part $part, array $overrides = []): array
{
    return [
        'supplier_id' => $supplier->id,
        'supplier_ref' => 'INV-778',
        'purchase_date' => now('Asia/Jakarta')->toDateString(),
        'due_date' => now('Asia/Jakarta')->addDays(30)->toDateString(),
        'lines' => [['part_id' => $part->id, 'qty' => '4', 'unit_cost' => 50_000]],
        ...$overrides,
    ];
}

describe('PurchaseService', function () {
    it('adds stock, numbers the purchase and starts it unpaid', function () {
        $shop = provisionTenant();
        $part = partIn($shop, ['cost_price' => 0]);
        $purchase = purchaseIn($shop, $part);

        expect($purchase->number)->toStartWith($shop->branch->code.'/PB/')
            ->and($purchase->total)->toBe(450_000)
            ->and($purchase->status)->toBe(PurchaseStatus::Unpaid)
            ->and(available($shop, $part))->toBe(1000)
            ->and($part->fresh()?->cost_price)->toBe(45_000);
    });

    it('keeps a weighted average cost over the stock already there', function () {
        $shop = provisionTenant();
        $part = partIn($shop, ['cost_price' => 0]);
        purchaseIn($shop, $part);

        inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->create(
            supplierIn($shop),
            ['purchase_date' => now()->toDateString()],
            [['part_id' => $part->id, 'qty' => 1000, 'unit_cost' => 55_000]],
        ));

        // (10 × 45.000 + 10 × 55.000) / 20
        expect($part->fresh()?->cost_price)->toBe(50_000)
            ->and(available($shop, $part))->toBe(2000);
    });

    it('prices decimal quantities, rounding half up', function () {
        $shop = provisionTenant();
        $oil = partIn($shop, ['unit' => 'liter']);

        $purchase = inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->create(
            supplierIn($shop),
            ['purchase_date' => now()->toDateString()],
            [['part_id' => $oil->id, 'qty' => 425, 'unit_cost' => 33_333]],
        ));

        // 4,25 × 33.333 = 141.665,25
        expect($purchase->total)->toBe(141_665)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => PurchaseLine::query()->where('purchase_id', $purchase->id)->value('qty')))->toBe('4.25');
    });

    it('rejects decimals for counted units', function () {
        $shop = provisionTenant();
        $part = partIn($shop, ['unit' => 'pcs']);

        expect(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->create(
            supplierIn($shop),
            ['purchase_date' => now()->toDateString()],
            [['part_id' => $part->id, 'qty' => 150, 'unit_cost' => 1_000]],
        )))->toThrow(BusinessRuleViolation::class);
    });

    it('pays right away from a cash account', function () {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop, 1_000_000);
        $part = partIn($shop);

        $purchase = inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->create(
            supplierIn($shop),
            ['purchase_date' => now()->toDateString()],
            [['part_id' => $part->id, 'qty' => 200, 'unit_cost' => 100_000]],
            ['amount' => 200_000, 'account' => $drawer],
        ));

        expect($purchase->status)->toBe(PurchaseStatus::Paid)
            ->and($purchase->outstanding())->toBe(0)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->balance($drawer)))->toBe(800_000);
    });

    it('saves nothing when the drawer does not have the money', function () {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop, 50_000);
        $part = partIn($shop);

        expect(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->create(
            supplierIn($shop),
            ['purchase_date' => now()->toDateString()],
            [['part_id' => $part->id, 'qty' => 100, 'unit_cost' => 100_000]],
            ['amount' => 100_000, 'account' => $drawer],
        )))->toThrow(BusinessRuleViolation::class);

        expect(inTenant($shop->tenant, $shop->branch, fn () => Purchase::query()->count()))->toBe(0)
            ->and(available($shop, $part))->toBe(0);
    });

    it('moves stock by the difference when a purchase is revised, with an audit entry', function () {
        $shop = provisionTenant();
        $part = partIn($shop);
        $purchase = purchaseIn($shop, $part);

        inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->revise(
            $purchase,
            ['due_date' => null],
            [['part_id' => $part->id, 'qty' => 800, 'unit_cost' => 45_000]],
            'Salah hitung',
        ));

        expect(available($shop, $part))->toBe(800)
            ->and($purchase->fresh()?->total)->toBe(360_000)
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'purchase_revised')->value('reason')))->toBe('Salah hitung');
    });

    it('refuses a revision below what was already paid', function () {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop);
        $part = partIn($shop);
        $purchase = purchaseIn($shop, $part);
        inTenant($shop->tenant, $shop->branch, fn () => app(SupplierPaymentService::class)->pay($purchase, 400_000, $drawer));

        expect(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->revise(
            $purchase,
            [],
            [['part_id' => $part->id, 'qty' => 500, 'unit_cost' => 45_000]],
            'Retur',
        )))->toThrow(BusinessRuleViolation::class);

        expect(available($shop, $part))->toBe(1000);
    });

    it('re-arms the due reminders when the due date changes', function () {
        $shop = provisionTenant();
        $part = partIn($shop);
        $purchase = purchaseIn($shop, $part, now()->addDay()->toDateString());
        $purchase->forceFill(['due_soon_notified_at' => now()])->saveQuietly();

        inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->revise(
            $purchase,
            ['due_date' => now()->addDays(20)->toDateString()],
            [['part_id' => $part->id, 'qty' => 1000, 'unit_cost' => 45_000]],
            'Tempo diperpanjang',
        ));

        expect($purchase->fresh()?->due_soon_notified_at)->toBeNull();
    });

    it('voids an unpaid purchase and takes its stock back out', function () {
        $shop = provisionTenant();
        $part = partIn($shop);
        $purchase = purchaseIn($shop, $part);

        inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->void($purchase, 'Dobel input'));

        expect($purchase->status)->toBe(PurchaseStatus::Void)
            ->and(available($shop, $part))->toBe(0)
            ->and(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->revise($purchase, [], [['part_id' => $part->id, 'qty' => 100, 'unit_cost' => 1]], 'x')))
            ->toThrow(BusinessRuleViolation::class);
    });

    it('does not void a purchase with payments, or one whose stock is already used', function () {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop);
        $paid = purchaseIn($shop);
        inTenant($shop->tenant, $shop->branch, fn () => app(SupplierPaymentService::class)->pay($paid, 100_000, $drawer));

        expect(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->void($paid, 'Salah')))->toThrow(BusinessRuleViolation::class);

        $part = partIn($shop);
        $used = purchaseIn($shop, $part);
        stock($shop, fn (StockService $stock) => $stock->move($part, StockMovementType::Adjustment, -500));

        expect(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->void($used, 'Salah')))->toThrow(BusinessRuleViolation::class)
            ->and($used->fresh()?->status)->toBe(PurchaseStatus::Unpaid);
    });
});

describe('SupplierPaymentService', function () {
    it('pays in parts and voids a payment, putting the money back', function () {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop, 1_000_000);
        $purchase = purchaseIn($shop);

        $first = inTenant($shop->tenant, $shop->branch, function () use ($purchase, $drawer) {
            $payments = app(SupplierPaymentService::class);
            $first = $payments->pay($purchase, 150_000, $drawer);
            expect($purchase->status)->toBe(PurchaseStatus::Partial);

            $payments->pay($purchase, 300_000, $drawer);
            expect($purchase->status)->toBe(PurchaseStatus::Paid);

            $payments->void($first, 'Nominal salah');

            return $first;
        });

        expect($first->isVoided())->toBeTrue()
            ->and($purchase->fresh()?->amount_paid)->toBe(300_000)
            ->and($purchase->fresh()?->status)->toBe(PurchaseStatus::Partial)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->balance($drawer)))->toBe(700_000)
            ->and(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(SupplierPaymentService::class)->void($first, 'Lagi')))->toThrow(BusinessRuleViolation::class);
    });

    it('refuses to pay more than is owed', function () {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop);
        $purchase = purchaseIn($shop);

        expect(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(SupplierPaymentService::class)->pay($purchase, 450_001, $drawer)))
            ->toThrow(BusinessRuleViolation::class);
    });
});

describe('HTTP', function () {
    it('lets Front Desk record a purchase and pay it in full from the drawer, up to the limit', function () {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop);
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $supplier = supplierIn($shop);
        $part = partIn($shop);

        // Default limit Rp 500.000; 4 × 50.000 = 200.000
        $this->actingAs($frontDesk)
            ->post(route('purchases.store'), validPurchase($supplier, $part, ['pay_amount' => 200_000, 'pay_account_id' => $drawer->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $purchase = inTenant($shop->tenant, $shop->branch, fn () => Purchase::query()->sole());

        expect($purchase->status)->toBe(PurchaseStatus::Paid)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => CashMovement::query()->where('type', 'supplier_payment')->sum('amount')))->toEqual(-200_000);
    });

    it('limits Front Desk to full cash payments within the branch limit', function (array $input, string $errorField) {
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop);
        $bank = accountIn($shop, 'bank');
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $supplier = supplierIn($shop);
        $part = partIn($shop);

        $accounts = ['drawer' => $drawer->id, 'bank' => $bank->id];
        $input['pay_account_id'] = $accounts[$input['pay_account_id']];
        if (isset($input['qty'])) {
            $input['lines'] = [['part_id' => $part->id, 'qty' => $input['qty'], 'unit_cost' => 50_000]];
            unset($input['qty']);
        }

        $this->actingAs($frontDesk)
            ->post(route('purchases.store'), validPurchase($supplier, $part, $input))
            ->assertSessionHasErrors($errorField);

        expect(inTenant($shop->tenant, $shop->branch, fn () => Purchase::query()->count()))->toBe(0);
    })->with([
        'partial payment' => [['pay_amount' => 100_000, 'pay_account_id' => 'drawer'], 'pay_amount'],
        'bank account' => [['pay_amount' => 200_000, 'pay_account_id' => 'bank'], 'pay_account_id'],
        'over the limit' => [['qty' => '20', 'pay_amount' => 1_000_000, 'pay_account_id' => 'drawer'], 'pay_amount'],
    ]);

    it('lets Front Desk save a large purchase unpaid, but not pay or see supplier debt later', function () {
        $shop = provisionTenant();
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $purchase = purchaseIn($shop);
        $drawer = fundedDrawer($shop);

        $this->actingAs($frontDesk)->get(route('purchases.show', $purchase))
            ->assertInertia(fn (Assert $page) => $page->where('can.pay', false)->where('can.update', false)->where('payAccounts', []));
        $this->actingAs($frontDesk)->post(route('purchases.payments.store', $purchase), [
            'amount' => 100_000, 'cash_account_id' => $drawer->id, 'paid_on' => now('Asia/Jakarta')->toDateString(),
        ])->assertForbidden();
        $this->actingAs($frontDesk)->get(route('supplier-debts.index'))->assertForbidden();
        $this->actingAs($frontDesk)->post(route('purchases.void', $purchase), ['reason' => 'x'])->assertForbidden();
    });

    it('lets Finance pay any amount from the bank later and list supplier debt', function () {
        $shop = provisionTenant();
        $finance = userWithRole($shop, RoleName::Finance);
        $bank = accountIn($shop, 'bank');
        $purchase = purchaseIn($shop, dueDate: now()->subDay()->toDateString());

        $this->actingAs($finance)->get(route('supplier-debts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Purchases/Debts')
                ->where('debts.data.0.number', $purchase->number)
                ->where('debts.data.0.is_overdue', true)
                ->where('outstandingLabel', 'Rp 450.000'));

        $this->actingAs($finance)->post(route('purchases.payments.store', $purchase), [
            'amount' => 100_000, 'cash_account_id' => $bank->id, 'paid_on' => now('Asia/Jakarta')->toDateString(), 'reference' => 'TRF-1',
        ])->assertSessionHasNoErrors();

        expect($purchase->fresh()?->amount_paid)->toBe(100_000);

        $this->actingAs($finance)->post(route('purchases.payments.store', $purchase), [
            'amount' => 100_000, 'cash_account_id' => $bank->id, 'paid_on' => now('Asia/Jakarta')->addDay()->toDateString(),
        ])->assertSessionHasErrors('paid_on');
    });

    it('revises through the form with a reason and audits it', function () {
        $shop = provisionTenant();
        $finance = userWithRole($shop, RoleName::Finance);
        $part = partIn($shop);
        $purchase = purchaseIn($shop, $part);

        $this->actingAs($finance)->put(route('purchases.update', $purchase), [
            'lines' => [['part_id' => $part->id, 'qty' => '12', 'unit_cost' => 45_000]],
        ])->assertSessionHasErrors('reason');

        $this->actingAs($finance)->put(route('purchases.update', $purchase), [
            'lines' => [['part_id' => $part->id, 'qty' => '12', 'unit_cost' => 45_000]],
            'reason' => 'Bonus 2 pcs',
        ])->assertRedirect(route('purchases.show', $purchase));

        expect(available($shop, $part))->toBe(1200);
    });

    it('only lets branch managers change the cash purchase limit', function () {
        $shop = provisionTenant();

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))
            ->put(route('settings.payments.cash-purchase-limit.update'), ['cash_purchase_limit' => 5_000_000])
            ->assertForbidden();

        $this->actingAs($shop->owner)
            ->put(route('settings.payments.cash-purchase-limit.update'), ['cash_purchase_limit' => 750_000])
            ->assertSessionHasNoErrors();
        expect($shop->branch->fresh()?->cash_purchase_limit)->toBe(750_000);

        $this->actingAs($shop->owner)
            ->put(route('settings.payments.cash-purchase-limit.update'), ['cash_purchase_limit' => null]);
        expect($shop->branch->fresh()?->cash_purchase_limit)->toBeNull();
    });

    it('shows the purchase cost in the part lookup only to users who may see costs', function () {
        $shop = provisionTenant();
        partIn($shop, ['name' => 'Kampas rem depan', 'cost_price' => 35_000]);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->getJson(route('parts.lookup', ['q' => 'kampas']))
            ->assertOk()->assertJsonPath('0.name', 'Kampas rem depan')->assertJsonPath('0.cost_price', null);
        $this->actingAs($shop->owner)->getJson(route('parts.lookup', ['q' => 'kampas']))
            ->assertJsonPath('0.cost_price', 35_000);
    });

    it('does not let users of another tenant see or pay a purchase', function () {
        $shop = provisionTenant();
        $purchase = purchaseIn($shop);
        $other = provisionTenant('Bengkel Lain');

        $this->actingAs($other->owner)->get(route('purchases.show', $purchase))->assertNotFound();
        $this->actingAs($other->owner)->post(route('purchases.void', $purchase), ['reason' => 'x'])->assertNotFound();
    });

    it('refuses a part of another tenant on a purchase line', function () {
        $shop = provisionTenant();
        $other = provisionTenant('Bengkel Lain');
        $foreignPart = partIn($other);

        $this->actingAs($shop->owner)
            ->post(route('purchases.store'), validPurchase(supplierIn($shop), $foreignPart))
            ->assertSessionHasErrors('lines.0.part_id');
    });
});

it('keeps purchases, lines and supplier payments isolated per tenant', function () {
    assertTenantIsolated(Purchase::class, fn (ProvisionedTenant $shop) => purchaseIn($shop));
    assertTenantIsolated(PurchaseLine::class, fn (ProvisionedTenant $shop) => purchaseIn($shop)->lines()->firstOrFail());
    assertTenantIsolated(SupplierPayment::class, fn (ProvisionedTenant $shop) => app(SupplierPaymentService::class)->pay(purchaseIn($shop), 1_000, fundedDrawer($shop)));
});

describe('due reminders', function () {
    it('notifies the users who pay suppliers once before the due date and once when overdue', function () {
        Notification::fake();
        $shop = provisionTenant();
        $finance = userWithRole($shop, RoleName::Finance);
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $soon = purchaseIn($shop, dueDate: now('Asia/Jakarta')->addDays(2)->toDateString());
        $late = purchaseIn($shop, dueDate: now('Asia/Jakarta')->subDay()->toDateString());
        purchaseIn($shop, dueDate: now('Asia/Jakarta')->addDays(10)->toDateString());

        expect(app(PurchaseDueNotifier::class)->run())->toBe(2)
            ->and(app(PurchaseDueNotifier::class)->run())->toBe(0);

        Notification::assertSentTo($finance, PurchaseDueNotification::class, fn ($n, $channels, $user) => $n->toArray($user)['kind'] === PurchaseDueNotification::DUE_SOON
            && $n->toArray($user)['purchase_id'] === $soon->id);
        Notification::assertSentTo($finance, PurchaseDueNotification::class, fn ($n, $channels, $user) => $n->toArray($user)['kind'] === PurchaseDueNotification::OVERDUE
            && $n->toArray($user)['purchase_id'] === $late->id);
        Notification::assertSentTo($shop->owner, PurchaseDueNotification::class);
        Notification::assertNotSentTo($frontDesk, PurchaseDueNotification::class);
    });

    it('does not remind about paid or cancelled purchases', function () {
        Notification::fake();
        $shop = provisionTenant();
        $drawer = fundedDrawer($shop);
        $paid = purchaseIn($shop, dueDate: now('Asia/Jakarta')->subDay()->toDateString());
        inTenant($shop->tenant, $shop->branch, fn () => app(SupplierPaymentService::class)->pay($paid, 450_000, $drawer));
        $void = purchaseIn($shop, dueDate: now('Asia/Jakarta')->subDay()->toDateString());
        inTenant($shop->tenant, $shop->branch, fn () => app(PurchaseService::class)->void($void, 'Salah'));

        expect(app(PurchaseDueNotifier::class)->run())->toBe(0);
    });

    it('shows the reminder in the reader\'s language and links to the purchase', function () {
        $shop = provisionTenant();
        $purchase = purchaseIn($shop, dueDate: now('Asia/Jakarta')->subDay()->toDateString());
        $supplier = inTenant($shop->tenant, $shop->branch, fn () => $purchase->supplier()->firstOrFail()->name);
        app(PurchaseDueNotifier::class)->run();

        $this->actingAs($shop->owner)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.title', fn (string $title) => str_contains($title, $supplier) && str_contains($title, 'lewat jatuh tempo'))
                ->where('items.data.0.body', fn (string $body) => str_contains($body, $purchase->number) && str_contains($body, 'Rp 450.000'))
                ->where('items.data.0.url', route('purchases.show', $purchase)));
    });
});
