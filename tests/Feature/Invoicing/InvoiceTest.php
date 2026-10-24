<?php

use App\Enums\InvoiceStatus;
use App\Enums\RoleName;
use App\Enums\WorkOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceRevision;
use App\Services\Inventory\StockService;
use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Invoicing\InvoiceService;
use App\Services\Tenancy\ProvisionedTenant;
use Inertia\Testing\AssertableInertia as Assert;

describe('InvoiceCalculator', function () {
    it('adds tax on top, rounding half up', function () {
        // 1.000.005 − 5 = 1.000.000; 11% = 110.000
        expect(InvoiceCalculator::totals([600_000, 400_005], 5, true, '11.00', false))
            ->toBe(['subtotal' => 1_000_005, 'discount_amount' => 5, 'tax_amount' => 110_000, 'total' => 1_110_000]);
    });

    it('finds the tax inside a tax-inclusive total', function () {
        // round(111.000 × 11 / 111) = 11.000
        expect(InvoiceCalculator::totals([111_000], 0, true, '11.00', true))
            ->toBe(['subtotal' => 111_000, 'discount_amount' => 0, 'tax_amount' => 11_000, 'total' => 111_000]);
    });

    it('shows no tax when tax is off, and refuses discounts above the amount', function () {
        expect(InvoiceCalculator::totals([50_000], 10_000, false, '11.00', false)['total'])->toBe(40_000)
            ->and(fn () => InvoiceCalculator::totals([50_000], 50_001, false, '11.00', false))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => InvoiceCalculator::lineTotal(200, 10_000, 20_001))->toThrow(BusinessRuleViolation::class)
            ->and(InvoiceCalculator::lineTotal(150, 10_001, 1))->toBe(15_001);
    });
});

describe('from a work order', function () {
    it('copies the lines, takes the agreed prices and locks the work order', function () {
        $shop = provisionTenant();
        $workOrder = completedWorkOrder($shop);

        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder(
            $workOrder,
            invoiceInput(['discount_amount' => 10_000]),
            pricesOf($shop, $workOrder, ['service' => ['unit_price' => 90_000], 'part' => ['discount_amount' => 5_000]]),
        ));

        expect($invoice->number)->toStartWith($shop->branch->code.'/INV/')
            ->and($invoice->customer_id)->toBe($workOrder->customer_id)
            // 90.000 + (100.000 − 5.000) − 10.000
            ->and($invoice->subtotal)->toBe(185_000)
            ->and($invoice->total)->toBe(175_000)
            ->and($invoice->tax_enabled)->toBeFalse()
            ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
            ->and($workOrder->fresh()?->status)->toBe(WorkOrderStatus::Invoiced)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => InvoiceLine::query()->where('type', 'part')->value('qty')))->toBe('2.00');
    });

    it('does not move stock again: the work order already took the parts', function () {
        $shop = provisionTenant();
        $workOrder = completedWorkOrder($shop);
        $part = inTenant($shop->tenant, $shop->branch, fn () => $workOrder->parts()->with('part')->firstOrFail()->part);
        $before = inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part));

        invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));

        expect(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part)))->toBe($before);
    });

    it('copies the branch tax setting, so later changes do not alter the invoice', function () {
        $shop = provisionTenant();
        $shop->branch->update(['tax_enabled' => true, 'tax_rate' => '11.00', 'tax_inclusive' => false]);
        $workOrder = completedWorkOrder($shop);

        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));
        $shop->branch->update(['tax_rate' => '12.00']);

        expect($invoice->fresh()?->tax_amount)->toBe(22_000)
            ->and($invoice->fresh()?->total)->toBe(222_000)
            ->and($invoice->fresh()?->tax_rate)->toBe('11.00');
    });

    it('refuses an unfinished work order, a second invoice, and a price list that does not match the work order', function () {
        $shop = provisionTenant();
        $open = workOrderIn($shop);
        $done = completedWorkOrder($shop);

        expect(fn () => invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($open, invoiceInput(), [])))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($done, invoiceInput(), array_slice(pricesOf($shop, $done), 0, 1))))->toThrow(BusinessRuleViolation::class);

        invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($done, invoiceInput(), pricesOf($shop, $done)));

        expect(fn () => invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($done, invoiceInput(), pricesOf($shop, $done))))->toThrow(BusinessRuleViolation::class);
    });

    it('sends the work order back to completed when its invoice is cancelled, so it can be invoiced again', function () {
        $shop = provisionTenant();
        $workOrder = completedWorkOrder($shop);
        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));

        invoices($shop, fn (InvoiceService $service) => $service->void($invoice, 'Harga salah'));

        expect($invoice->status)->toBe(InvoiceStatus::Void)
            ->and($workOrder->fresh()?->status)->toBe(WorkOrderStatus::Completed)
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'invoice_voided')->value('reason')))->toBe('Harga salah');

        $again = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));
        expect($again->status)->toBe(InvoiceStatus::Unpaid);
    });
});

describe('walk-in sale', function () {
    it('sells parts to "Pelanggan umum" and takes them from the stock', function () {
        $shop = provisionTenant();
        $oil = stockedPart($shop, 2000, ['unit' => 'liter', 'name' => 'Oli mesin']);

        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createWalkIn(null, invoiceInput(), [
            ['type' => 'part', 'part_id' => $oil->id, 'qty' => 400, 'unit_price' => 80_000],
            ['type' => 'other', 'description' => 'Ongkos antar', 'qty' => 100, 'unit_price' => 15_000],
        ]));

        expect($invoice->customer_id)->toBeNull()
            ->and($invoice->total)->toBe(335_000)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($oil)))->toBe(1600)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => InvoiceLine::query()->where('type', 'part')->value('description')))->toBe('Oli mesin');

        invoices($shop, fn (InvoiceService $service) => $service->void($invoice, 'Batal beli'));

        expect(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($oil)))->toBe(2000);
    });

    it('refuses parts that are not in stock, and saves nothing then', function () {
        $shop = provisionTenant();
        $part = stockedPart($shop, 100);

        expect(fn () => invoices($shop, fn (InvoiceService $service) => $service->createWalkIn(null, invoiceInput(), [
            ['type' => 'part', 'part_id' => $part->id, 'qty' => 200, 'unit_price' => 1],
        ])))->toThrow(BusinessRuleViolation::class);

        expect(inTenant($shop->tenant, $shop->branch, fn () => Invoice::query()->count()))->toBe(0);
    });
});

describe('HTTP', function () {
    it('lets Front Desk invoice a work order with a discount, but not change the tax', function () {
        $shop = provisionTenant();
        $shop->branch->update(['tax_enabled' => true]);
        $workOrder = completedWorkOrder($shop);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->post(route('invoices.store'), [
            'mode' => 'work_order',
            'work_order_id' => $workOrder->id,
            'issue_date' => now('Asia/Jakarta')->toDateString(),
            'discount_amount' => 20_000,
            'tax_enabled' => false,
            'lines' => pricesOf($shop, $workOrder),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $invoice = inTenant($shop->tenant, $shop->branch, fn () => Invoice::query()->sole());

        // 200.000 − 20.000 = 180.000 + 11% (the branch setting still applies)
        expect($invoice->tax_enabled)->toBeTrue()
            ->and($invoice->total)->toBe(199_800);
    });

    it('lets Finance turn tax off for one invoice', function () {
        $shop = provisionTenant();
        $shop->branch->update(['tax_enabled' => true]);
        $workOrder = completedWorkOrder($shop);

        $this->actingAs(userWithRole($shop, RoleName::Finance))->post(route('invoices.store'), [
            'mode' => 'work_order', 'work_order_id' => $workOrder->id, 'issue_date' => now('Asia/Jakarta')->toDateString(),
            'tax_enabled' => false, 'lines' => pricesOf($shop, $workOrder),
        ])->assertSessionHasNoErrors();

        expect(inTenant($shop->tenant, $shop->branch, fn () => Invoice::query()->sole()->total))->toBe(200_000);
    });

    it('sells at the counter without a customer', function () {
        $shop = provisionTenant();
        $part = stockedPart($shop);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->post(route('invoices.store'), [
            'mode' => 'walk_in',
            'issue_date' => now('Asia/Jakarta')->toDateString(),
            'lines' => [['type' => 'part', 'part_id' => $part->id, 'qty' => '1', 'unit_price' => 50_000]],
        ])->assertSessionHasNoErrors();

        $invoice = inTenant($shop->tenant, $shop->branch, fn () => Invoice::query()->sole());

        $this->actingAs($shop->owner)->get(route('invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page->component('Invoices/Show')->where('invoice.customer', null)->where('invoice.total_label', 'Rp 50.000'));
    });

    it('only lets Finance cancel, and never an invoice of another branch or tenant', function () {
        $shop = provisionTenant();
        $workOrder = completedWorkOrder($shop);
        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->post(route('invoices.void', $invoice), ['reason' => 'x'])->assertForbidden();
        $this->actingAs(provisionTenant('Bengkel Lain')->owner)->get(route('invoices.show', $invoice))->assertNotFound();

        $east = addBranch($shop);
        $this->actingAs(userWithRole($shop, RoleName::Finance, [$east]))->get(route('invoices.show', $invoice))->assertNotFound();

        $this->actingAs(userWithRole($shop, RoleName::Finance))->post(route('invoices.void', $invoice), ['reason' => 'Salah input'])->assertSessionHasNoErrors();
        expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Void);
    });

    it('lets the branch manager decide whether invoices carry tax', function () {
        $shop = provisionTenant();
        $settings = ['tax_enabled' => true, 'tax_name' => 'PPN', 'tax_rate' => '11,5', 'tax_inclusive' => true, 'invoice_footer' => 'Terima kasih'];

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->put(route('settings.invoice.update'), $settings)->assertForbidden();
        $this->actingAs(userWithRole($shop, RoleName::BranchManager))->put(route('settings.invoice.update'), $settings)->assertSessionHasNoErrors();
        $this->actingAs($shop->owner)->put(route('settings.invoice.update'), [...$settings, 'tax_rate' => '101'])->assertSessionHasErrors('tax_rate');

        $branch = $shop->branch->fresh();
        expect($branch?->tax_enabled)->toBeTrue()
            ->and($branch?->tax_rate)->toBe('11.50')
            ->and($branch?->tax_inclusive)->toBeTrue()
            ->and($branch?->invoice_footer)->toBe('Terima kasih');
    });

    it('prints the invoice for staff who can see it', function () {
        $shop = provisionTenant();
        $workOrder = completedWorkOrder($shop);
        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('invoices.print', $invoice))
            ->assertInertia(fn (Assert $page) => $page->component('Invoices/Print')->where('invoice.number', $invoice->number)->has('invoice.lines', 2));
        $this->actingAs(userWithRole($shop, RoleName::Warehouse))->get(route('invoices.print', $invoice))->assertForbidden();
    });
});

it('keeps invoices, lines and revisions isolated per tenant', function () {
    $make = fn (ProvisionedTenant $shop) => app(InvoiceService::class)->createWalkIn(null, invoiceInput(), [['type' => 'other', 'description' => 'x', 'qty' => 100, 'unit_price' => 1]]);

    assertTenantIsolated(Invoice::class, $make);
    assertTenantIsolated(InvoiceLine::class, fn (ProvisionedTenant $shop) => $make($shop)->lines()->firstOrFail());
    assertTenantIsolated(InvoiceRevision::class, fn (ProvisionedTenant $shop) => InvoiceRevision::query()->create([
        'invoice_id' => $make($shop)->id, 'revision_no' => 1, 'snapshot' => ['total' => 1], 'reason' => 'x',
    ]));
});
