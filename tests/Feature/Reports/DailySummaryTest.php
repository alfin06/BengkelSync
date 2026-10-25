<?php

use App\Enums\RoleName;
use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Cash\CashService;
use App\Services\Expenses\ExpenseService;
use App\Services\Invoicing\InvoiceService;
use App\Services\Payments\PaymentService;
use App\Services\Reports\DailySummaryService;
use App\Services\Tenancy\ProvisionedTenant;
use Inertia\Testing\AssertableInertia as Assert;

function drawerOf(ProvisionedTenant $shop): CashAccount
{
    return inTenant($shop->tenant, $shop->branch, fn () => CashAccount::query()->where('type', 'cash_drawer')->sole());
}

/**
 * A counter sale of Rp 100.000 today, Rp 60.000 of it paid in cash.
 */
function partlyPaidSale(ProvisionedTenant $shop): Invoice
{
    $part = stockedPart($shop, 1000);
    $invoice = invoices($shop, fn (InvoiceService $service) => $service->createWalkIn(null, invoiceInput(), [
        ['type' => 'part', 'part_id' => $part->id, 'qty' => 200, 'unit_price' => 50_000],
    ]));

    payments($shop, fn (PaymentService $service) => $service->checkout(
        $invoice,
        now('Asia/Jakarta')->toDateString(),
        [['method' => inTenant($shop->tenant, $shop->branch, fn () => PaymentMethod::query()->with('account')->where('type', 'cash')->firstOrFail()), 'amount' => 60_000]],
        now('Asia/Jakarta')->addWeek()->toDateString(),
    ));

    return $invoice->fresh() ?? $invoice;
}

function spend(ProvisionedTenant $shop, string $category, int $amount): void
{
    inTenant($shop->tenant, $shop->branch, fn () => app(ExpenseService::class)->create($shop->owner, [
        'expense_date' => now('Asia/Jakarta')->toDateString(),
        'category_id' => ExpenseCategory::query()->where('name', $category)->value('id'),
        'description' => $category,
        'amount' => $amount,
        'cash_account_id' => drawerOf($shop)->id,
    ]));
}

/**
 * @return array<string, mixed>
 */
function summaryFor(ProvisionedTenant $shop, $user, ?string $date = null): array
{
    return inTenant($shop->tenant, $shop->branch, fn () => app(DailySummaryService::class)->for($user, $date ?? now('Asia/Jakarta')->toDateString()));
}

it('adds up the day: sales, money received, expenses and the cash drawer from opening to closing', function () {
    $shop = provisionTenant();
    $drawer = drawerOf($shop);

    // Yesterday's closing is today's opening balance.
    $this->travelTo(now()->subDay());
    inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->countTo($drawer, 100_000, 'Saldo awal'));
    $this->travelBack();

    partlyPaidSale($shop);
    spend($shop, 'Bensin & transport', 20_000);
    spend($shop, 'Gaji & bonus', 30_000);

    $summary = summaryFor($shop, $shop->owner);
    $cash = collect($summary['cash'])->firstWhere('name', $drawer->name);

    expect($summary['sales'])->toMatchArray(['count' => 1, 'sales_label' => 'Rp 100.000', 'unpaid_count' => 1, 'unpaid_label' => 'Rp 40.000'])
        ->and($summary['received']['total_label'])->toBe('Rp 60.000')
        ->and($summary['received']['methods'][0]['count'])->toBe(1)
        ->and($summary['expenses']['total_label'])->toBe('Rp 50.000')
        ->and($cash)->toMatchArray(['opening_label' => 'Rp 100.000', 'in_label' => 'Rp 60.000', 'out_label' => 'Rp 50.000', 'closing_label' => 'Rp 110.000']);

    // Finance does not see salaries in the expense list (the drawer still shows the money left).
    $finance = summaryFor($shop, userWithRole($shop, RoleName::Finance));
    expect($finance['expenses']['total_label'])->toBe('Rp 20.000')
        ->and($finance['expenses']['includes_restricted'])->toBeFalse();

    // Yesterday only shows the opening count.
    $yesterday = summaryFor($shop, $shop->owner, now('Asia/Jakarta')->subDay()->toDateString());
    expect($yesterday['sales']['count'])->toBe(0)
        ->and(collect($yesterday['cash'])->firstWhere('name', $drawer->name)['closing_label'])->toBe('Rp 100.000');
});

it('lists the day\'s cancellations with their reason', function () {
    $shop = provisionTenant();
    $invoice = partlyPaidSale($shop);
    $payment = inTenant($shop->tenant, $shop->branch, fn () => Payment::query()->where('invoice_id', $invoice->id)->sole());

    payments($shop, fn (PaymentService $service) => $service->voidPayment($payment, 'Salah metode'));

    $summary = summaryFor($shop, $shop->owner);
    expect($summary['cancellations'])->toHaveCount(1)
        ->and($summary['cancellations'][0])->toMatchArray(['kind' => 'payment', 'amount_label' => 'Rp 60.000', 'reason' => 'Salah metode'])
        ->and($summary['received']['total_label'])->toBe('Rp 0');
});

it('is for Owner, branch managers and Finance, and only counts this shop', function () {
    $shop = provisionTenant();
    partlyPaidSale($shop);
    partlyPaidSale(provisionTenant('Bengkel Lain'));

    $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('reports.daily'))->assertForbidden();
    $this->actingAs(userWithRole($shop, RoleName::BranchManager))->get(route('reports.daily'))->assertOk();
    $this->actingAs(userWithRole($shop, RoleName::Finance))->get(route('reports.daily'))
        ->assertInertia(fn (Assert $page) => $page->component('Reports/Daily')->where('summary.sales.count', 1));

    $this->actingAs($shop->owner)->get(route('reports.daily', ['date' => now('Asia/Jakarta')->addDay()->toDateString()]))->assertSessionHasErrors('date');
    $this->actingAs($shop->owner)->get(route('reports.daily.print'))->assertInertia(fn (Assert $page) => $page->component('Reports/DailyPrint')->where('shop.name', $shop->tenant->name));
});
