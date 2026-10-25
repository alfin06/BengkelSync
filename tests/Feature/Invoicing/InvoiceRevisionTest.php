<?php

use App\Enums\InvoiceStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Enums\WorkOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceRevision;
use App\Models\Part;
use App\Models\PaymentMethod;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use App\Services\Invoicing\InvoiceService;
use App\Services\Invoicing\RevisionComparer;
use App\Services\Payments\PaymentService;
use App\Services\Tenancy\ProvisionedTenant;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The lines of an invoice as the edit form sends them back.
 *
 * @return list<array<string, mixed>>
 */
function linesOf(ProvisionedTenant $shop, Invoice $invoice): array
{
    return inTenant($shop->tenant, $shop->branch, fn () => $invoice->lines()->get()->map(fn (InvoiceLine $line) => [
        'id' => $line->id,
        'type' => $line->type->value,
        'part_id' => $line->part_id,
        'service_item_id' => $line->service_item_id,
        'description' => $line->description,
        'qty' => $line->qtyHundredths(),
        'unit_price' => $line->unit_price,
        'discount_amount' => $line->discount_amount,
    ])->all());
}

function shelf(ProvisionedTenant $shop, Part $part): int
{
    return inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part));
}

/**
 * A counter sale of 2 pcs at Rp 50.000 (Rp 100.000).
 *
 * @return array{Invoice, Part}
 */
function counterSale(ProvisionedTenant $shop): array
{
    $part = stockedPart($shop, 1000);
    $invoice = invoices($shop, fn (InvoiceService $service) => $service->createWalkIn(null, invoiceInput(), [
        ['type' => 'part', 'part_id' => $part->id, 'qty' => 200, 'unit_price' => 50_000],
    ]));

    return [$invoice, $part];
}

function payCash(ProvisionedTenant $shop, Invoice $invoice, int $amount): void
{
    payments($shop, fn (PaymentService $service) => $service->checkout(
        $invoice->fresh() ?? $invoice,
        now('Asia/Jakarta')->toDateString(),
        [['method' => inTenant($shop->tenant, $shop->branch, fn () => PaymentMethod::query()->with('account')->where('type', 'cash')->firstOrFail()), 'amount' => $amount]],
        now('Asia/Jakarta')->addWeek()->toDateString(),
    ));
}

describe('revise', function () {
    it('renames a part line without touching the stock', function () {
        $shop = provisionTenant();
        [$invoice, $part] = counterSale($shop);
        $before = shelf($shop, $part);
        $lines = linesOf($shop, $invoice);
        $lines[0]['description'] = 'Aki kering 45Ah (garansi 1 tahun)';

        invoices($shop, fn (InvoiceService $service) => $service->revise($invoice, [], $lines, 'Nama barang di nota'));

        expect(inTenant($shop->tenant, $shop->branch, fn () => $invoice->lines()->sole()->description))->toBe('Aki kering 45Ah (garansi 1 tahun)')
            ->and(shelf($shop, $part))->toBe($before);
    });

    it('saves the previous version, moves stock by the difference and recalculates', function () {
        $shop = provisionTenant();
        [$invoice, $part] = counterSale($shop);
        $lines = linesOf($shop, $invoice);
        $lines[0]['qty'] = 300;
        $lines[] = ['type' => 'service', 'description' => 'Pasang', 'qty' => 100, 'unit_price' => 20_000, 'discount_amount' => 0];

        invoices($shop, fn (InvoiceService $service) => $service->revise($invoice, ['discount_amount' => 5_000], $lines, 'Pelanggan tambah 1 pcs'));

        $invoice->refresh();
        $revision = inTenant($shop->tenant, $shop->branch, fn () => InvoiceRevision::query()->sole());

        expect($invoice->revision_no)->toBe(1)
            ->and($invoice->number)->toEndWith('00001')
            ->and($invoice->total)->toBe(165_000)
            ->and(shelf($shop, $part))->toBe(700)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => StockMovement::query()->where('type', StockMovementType::InvoiceEdit)->value('qty')))->toBe('-1.00')
            ->and($revision->revision_no)->toBe(0)
            ->and($revision->reason)->toBe('Pelanggan tambah 1 pcs')
            ->and($revision->snapshot['total'])->toBe(100_000)
            ->and($revision->snapshot['lines'])->toHaveCount(1)
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'invoice_revised')->value('reason')))->toBe('Pelanggan tambah 1 pcs');
    });

    it('puts removed parts back on the shelf', function () {
        $shop = provisionTenant();
        [$invoice, $part] = counterSale($shop);

        invoices($shop, fn (InvoiceService $service) => $service->revise($invoice, [], [
            ['type' => 'other', 'description' => 'Ongkos', 'qty' => 100, 'unit_price' => 10_000],
        ], 'Part dikembalikan'));

        expect(shelf($shop, $part))->toBe(1000)
            ->and($invoice->fresh()?->total)->toBe(10_000);
    });

    it('keeps the cost and the mechanic of lines from the work order', function () {
        $shop = provisionTenant();
        $mechanic = mechanicIn($shop);
        $workOrder = completedWorkOrder($shop);
        inTenant($shop->tenant, $shop->branch, fn () => $workOrder->services()->update(['mechanic_id' => $mechanic->id]));
        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));
        $costBefore = inTenant($shop->tenant, $shop->branch, fn () => InvoiceLine::query()->where('type', 'part')->value('unit_cost'));
        inTenant($shop->tenant, $shop->branch, fn () => Part::query()->update(['cost_price' => 1]));

        $lines = linesOf($shop, $invoice);
        $lines[0]['unit_price'] = 80_000;
        invoices($shop, fn (InvoiceService $service) => $service->revise($invoice, [], $lines, 'Harga jasa salah'));

        expect(inTenant($shop->tenant, $shop->branch, fn () => InvoiceLine::query()->where('type', 'service')->value('mechanic_id')))->toBe($mechanic->id)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => InvoiceLine::query()->where('type', 'part')->value('unit_cost')))->toBe($costBefore)
            ->and($invoice->fresh()?->total)->toBe(180_000);
    });

    it('refuses a total below what is already paid, and changes nothing then', function () {
        $shop = provisionTenant();
        [$invoice, $part] = counterSale($shop);
        payCash($shop, $invoice, 80_000);
        $lines = linesOf($shop, $invoice);
        $lines[0]['qty'] = 100;

        expect(fn () => invoices($shop, fn (InvoiceService $service) => $service->revise($invoice->fresh(), [], $lines, 'Kurangi')))->toThrow(BusinessRuleViolation::class);

        expect(shelf($shop, $part))->toBe(800)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => InvoiceRevision::query()->count()))->toBe(0)
            ->and($invoice->fresh()?->revision_no)->toBe(0);
    });

    it('updates the status after an edit: a paid invoice that grows owes the difference again', function () {
        $shop = provisionTenant();
        $workOrder = completedWorkOrder($shop);
        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));
        payCash($shop, $invoice, 200_000);
        expect($workOrder->fresh()?->status)->toBe(WorkOrderStatus::Closed);

        $lines = linesOf($shop, $invoice);
        $lines[] = ['type' => 'other', 'description' => 'Ongkos derek', 'qty' => 100, 'unit_price' => 50_000, 'discount_amount' => 0];
        invoices($shop, fn (InvoiceService $service) => $service->revise($invoice->fresh(), ['due_date' => now('Asia/Jakarta')->addWeek()->toDateString()], $lines, 'Lupa ongkos derek'));

        expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Partial)
            ->and($invoice->fresh()?->outstanding())->toBe(50_000)
            ->and($workOrder->fresh()?->status)->toBe(WorkOrderStatus::Invoiced);
    });

    it('gives back what an edit added when the invoice of a work order is cancelled', function () {
        $shop = provisionTenant();
        $workOrder = completedWorkOrder($shop);
        $part = inTenant($shop->tenant, $shop->branch, fn () => $workOrder->parts()->with('part')->firstOrFail()->part);
        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));
        $afterWorkOrder = shelf($shop, $part);

        $lines = linesOf($shop, $invoice);
        $lines[1]['qty'] = 500;
        invoices($shop, fn (InvoiceService $service) => $service->revise($invoice, [], $lines, 'Tambah 3 pcs'));
        expect(shelf($shop, $part))->toBe($afterWorkOrder - 300);

        invoices($shop, fn (InvoiceService $service) => $service->void($invoice->fresh(), 'Salah'));

        expect(shelf($shop, $part))->toBe($afterWorkOrder)
            ->and($workOrder->fresh()?->status)->toBe(WorkOrderStatus::Completed);
    });

    it('never edits a cancelled invoice', function () {
        $shop = provisionTenant();
        [$invoice] = counterSale($shop);
        invoices($shop, fn (InvoiceService $service) => $service->void($invoice, 'Batal'));

        expect(fn () => invoices($shop, fn (InvoiceService $service) => $service->revise($invoice->fresh(), [], linesOf($shop, $invoice), 'x')))->toThrow(BusinessRuleViolation::class);
    });
});

describe('HTTP', function () {
    it('lets Front Desk correct an unpaid invoice, with a reason', function () {
        $shop = provisionTenant();
        [$invoice] = counterSale($shop);
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $lines = array_map(fn (array $line) => [...$line, 'qty' => '2', 'unit_price' => 45_000], linesOf($shop, $invoice));

        $this->actingAs($frontDesk)->put(route('invoices.update', $invoice), ['lines' => $lines])->assertSessionHasErrors('reason');
        $this->actingAs($frontDesk)->put(route('invoices.update', $invoice), ['lines' => $lines, 'reason' => 'Harga promo'])->assertRedirect(route('invoices.show', $invoice));

        expect($invoice->fresh()?->total)->toBe(90_000);
    });

    it('leaves invoices with payments to Finance', function () {
        $shop = provisionTenant();
        [$invoice] = counterSale($shop);
        payCash($shop, $invoice, 10_000);
        $lines = array_map(fn (array $line) => [...$line, 'qty' => '2'], linesOf($shop, $invoice));

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs(userWithRole($shop, RoleName::Finance))->put(route('invoices.update', $invoice), [
            'lines' => $lines, 'reason' => 'Koreksi', 'due_date' => now('Asia/Jakarta')->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();

        expect($invoice->fresh()?->revision_no)->toBe(1);
    });

    it('shows each edit with its reason and what changed', function () {
        $shop = provisionTenant();
        [$invoice] = counterSale($shop);
        $lines = linesOf($shop, $invoice);
        $lines[0]['unit_price'] = 40_000;
        invoices($shop, fn (InvoiceService $service) => $service->revise($invoice, [], $lines, 'Diskon langganan'));

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('invoices.revisions', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Revisions')
                ->has('history', 1)
                ->where('history.0.version', 1)
                ->where('history.0.reason', 'Diskon langganan')
                ->where('history.0.total_before', 'Rp 100.000')
                ->where('history.0.total_after', 'Rp 80.000')
                ->where('history.0.changes.lines.0.change', 'changed'));

        $this->actingAs(provisionTenant('Bengkel Lain')->owner)->get(route('invoices.revisions', $invoice))->assertNotFound();
    });
});

it('lists added, removed and changed lines between two versions', function () {
    $line = fn (array $overrides) => ['type' => 'part', 'part_id' => 1, 'description' => 'Busi', 'qty' => '2.00', 'unit_label' => 'pcs', 'unit_price' => 25_000, 'discount_amount' => 0, 'line_total' => 50_000, ...$overrides];
    $before = ['subtotal' => 80_000, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 80_000, 'lines' => [
        $line([]),
        $line(['type' => 'service', 'part_id' => null, 'description' => 'Tune up', 'qty' => '1.00', 'unit_label' => null, 'unit_price' => 30_000, 'line_total' => 30_000]),
    ]];
    $after = ['subtotal' => 95_000, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 95_000, 'lines' => [
        $line(['qty' => '3.00', 'line_total' => 75_000]),
        $line(['type' => 'other', 'part_id' => null, 'description' => 'Ongkos', 'qty' => '1.00', 'unit_label' => null, 'unit_price' => 20_000, 'line_total' => 20_000]),
    ]];

    $changes = RevisionComparer::compare($before, $after);

    expect(array_column($changes['lines'], 'change'))->toBe(['changed', 'removed', 'added'])
        ->and($changes['lines'][0]['before'])->toBe('2 pcs × Rp 25.000 = Rp 50.000')
        ->and($changes['lines'][0]['after'])->toBe('3 pcs × Rp 25.000 = Rp 75.000')
        ->and($changes['totals'])->toBe([
            ['field' => 'subtotal', 'before' => 'Rp 80.000', 'after' => 'Rp 95.000'],
            ['field' => 'total', 'before' => 'Rp 80.000', 'after' => 'Rp 95.000'],
        ]);
});
