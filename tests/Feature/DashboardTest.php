<?php

use App\Enums\CashMovementType;
use App\Enums\PaymentMethodType;
use App\Enums\RoleName;
use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Services\Cash\CashService;
use App\Services\Expenses\ExpenseService;
use App\Services\Invoicing\InvoiceService;
use App\Services\Payments\PaymentService;
use App\Services\Tenancy\ProvisionedTenant;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A known day in the shop:
 * - one invoice: job Rp 100.000 + 2 parts at Rp 50.000 (cost Rp 35.000 each), PPN 11% on
 *   top → total Rp 222.000, sales without tax Rp 200.000, parts cost Rp 70.000;
 * - Rp 100.000 of it paid by credit card with a 3% fee (Rp 3.000);
 * - expenses: Rp 50.000 fuel from petty cash and a Rp 500.000 salary (restricted).
 */
function busyDay(ProvisionedTenant $shop): void
{
    $shop->branch->update(['tax_enabled' => true, 'tax_rate' => '11.00', 'tax_inclusive' => false]);
    $workOrder = completedWorkOrder($shop);
    $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));

    inTenant($shop->tenant, $shop->branch, function () use ($shop, $invoice) {
        $card = PaymentMethod::factory()->create([
            'type' => PaymentMethodType::CreditCard,
            'cash_account_id' => accountIn($shop, 'bank')->id,
            'surcharge_percent' => '3.00',
        ])->load('account');
        app(PaymentService::class)->checkout($invoice, now('Asia/Jakarta')->toDateString(), [['method' => $card, 'amount' => 100_000]], now('Asia/Jakarta')->addWeek()->toDateString());

        $petty = CashAccount::query()->where('type', 'petty_cash')->firstOrFail();
        app(CashService::class)->record($petty, CashMovementType::TransferIn, 1_000_000);
        $category = fn (string $name) => ExpenseCategory::query()->where('name', $name)->value('id');
        $expense = fn (string $name, int $amount) => app(ExpenseService::class)->create($shop->owner, [
            'expense_date' => now('Asia/Jakarta')->toDateString(), 'category_id' => $category($name), 'description' => 'x', 'amount' => $amount, 'cash_account_id' => $petty->id,
        ]);
        $expense('Bensin & transport', 50_000);
        $expense('Gaji & bonus', 500_000);
    });
}

it('gives the owner the money figures, with profit after every expense', function () {
    $shop = provisionTenant();
    busyDay($shop);

    $this->actingAs($shop->owner)->get(route('dashboard', ['period' => 'today']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('financials.sales_label', 'Rp 200.000')
            ->where('financials.tax_label', 'Rp 22.000')
            ->where('financials.invoice_count', 1)
            ->where('financials.received_label', 'Rp 100.000')
            ->where('financials.surcharge_label', 'Rp 3.000')
            ->where('financials.expenses_label', 'Rp 550.000')
            ->where('financials.profit.parts_cost_label', 'Rp 70.000')
            // 200.000 − 70.000 − 550.000
            ->where('financials.profit.profit', -420_000)
            ->has('financials.monthly', 12)
            ->where('financials.top.services.0.name', 'Ganti kampas rem')
            ->where('financials.top.parts.0.revenue_label', 'Rp 100.000'));
});

it('shows Finance the money figures but no profit and no salaries', function () {
    $shop = provisionTenant();
    busyDay($shop);

    $this->actingAs(userWithRole($shop, RoleName::Finance))->get(route('dashboard', ['period' => 'today']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('financials.sales_label', 'Rp 200.000')
            ->where('financials.expenses_label', 'Rp 50.000')
            ->where('financials.expenses_include_restricted', false)
            ->where('financials.profit', null));

    // Branch managers do not get profit by default either.
    $this->actingAs(userWithRole($shop, RoleName::BranchManager))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('financials.profit', null));
});

it('shows Front Desk the workshop, cash drawers and what is owed, without money totals', function () {
    $shop = provisionTenant();
    busyDay($shop);
    workOrderIn($shop);

    $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('financials', null)
            ->where('supplierDue', null)
            ->where('workshop.statuses.queued', 1)
            ->where('workshop.checked_in_today', 2)
            ->where('receivables.count', 1)
            ->where('receivables.total_label', 'Rp 122.000')
            // Only the cash drawer and petty cash, not the bank.
            ->where('cash', fn ($accounts) => collect($accounts)->every(fn ($account) => $account['is_physical'])));
});

it('shows Warehouse the low stock but not the workshop or cash', function () {
    $shop = provisionTenant();
    $part = stockedPart($shop, 100, ['name' => 'Busi']);
    stock($shop, fn ($stock) => $stock->configure($part, 300, null));

    $this->actingAs(userWithRole($shop, RoleName::Warehouse))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workshop', null)
            ->where('cash', null)
            ->where('lowStock.count', 1)
            ->where('lowStock.items.0.name', 'Busi'));
});

it('counts only the period asked for, and only this branch', function () {
    $shop = provisionTenant();
    busyDay($shop);
    $yesterday = now('Asia/Jakarta')->subDay()->toDateString();

    $this->actingAs($shop->owner)->get(route('dashboard', ['period' => 'custom', 'from' => $yesterday, 'to' => $yesterday]))
        ->assertInertia(fn (Assert $page) => $page->where('financials.sales_label', 'Rp 0')->where('filters.from', $yesterday));

    $east = addBranch($shop);
    $this->actingAs(userWithRole($shop, RoleName::Finance, [$east]))->get(route('dashboard', ['period' => 'today']))
        ->assertInertia(fn (Assert $page) => $page->where('financials.sales_label', 'Rp 0')->where('receivables.count', 0));

    $this->actingAs($shop->owner)->get(route('dashboard', ['period' => 'custom', 'from' => '2026-10-10']))->assertSessionHasErrors('to');
});
