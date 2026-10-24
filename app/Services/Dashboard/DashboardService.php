<?php

namespace App\Services\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\PurchaseStatus;
use App\Enums\WorkOrderStatus;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PartBranchStock;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Cash\CashService;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The numbers on the home page, for the active branch (SPEC section 7, "single branch
 * view"). Every query goes through the models, so tenant and branch scopes apply.
 *
 * Definitions (pilot decisions):
 * - "Penjualan" = invoices made in the period (not cancelled), without tax;
 * - "Uang masuk" = payments received in the period (not cancelled), without card fees;
 * - card fees are shown apart: the bank fee eats them;
 * - gross profit = sales − cost of the parts sold − all expenses (salaries included),
 *   only with dashboard.view_profit (Owner).
 */
class DashboardService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CashService $cash,
    ) {}

    /**
     * @return array{statuses: array<string, int>, checked_in_today: int, due_today: list<array<string, mixed>>}
     */
    public function workshop(): array
    {
        $timezone = $this->context->timezone();
        $todayStart = now($timezone)->startOfDay()->utc();
        $todayEnd = now($timezone)->endOfDay()->utc();
        $open = [WorkOrderStatus::Queued, WorkOrderStatus::InProgress, WorkOrderStatus::WaitingParts];

        /** @var array<string, int> $statuses */
        $statuses = WorkOrder::query()
            ->whereIn('status', [...$open, WorkOrderStatus::Completed])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        // Promised for today and not finished yet.
        $dueToday = WorkOrder::query()
            ->with('vehicle')
            ->whereIn('status', $open)
            ->whereBetween('estimated_completion', [$todayStart, $todayEnd])
            ->orderBy('estimated_completion')
            ->limit(5)
            ->get()
            ->map(fn (WorkOrder $workOrder) => [
                'id' => $workOrder->id,
                'plate_number' => $workOrder->vehicle->plate_number,
                'status' => $workOrder->status->value,
                'status_label' => $workOrder->status->label(),
                'time' => $workOrder->estimated_completion?->copy()->setTimezone($timezone)->format('H:i'),
            ])
            ->all();

        return [
            'statuses' => $statuses,
            'checked_in_today' => WorkOrder::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'due_today' => $dueToday,
        ];
    }

    /**
     * Parts at or below their threshold in this branch, emptiest first.
     *
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    public function lowStock(): array
    {
        $low = PartBranchStock::query()
            ->whereColumn('qty', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->whereHas('part', fn (Builder $part) => $part->where('is_active', true));

        return [
            'count' => $low->clone()->count(),
            'items' => $low->clone()->with('part')->orderBy('qty')->limit(5)->get()->map(fn (PartBranchStock $stock) => [
                'part_id' => $stock->part_id,
                'name' => $stock->part->name,
                'qty_label' => Quantity::format($stock->qtyHundredths()).' '.$stock->part->unit->label(),
                'empty' => $stock->qtyHundredths() <= 0,
            ])->all(),
        ];
    }

    /**
     * What customers still owe, and how much of it is overdue.
     *
     * @return array{count: int, total_label: string, overdue_count: int, overdue_label: string}
     */
    public function receivables(): array
    {
        $open = Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
        $overdue = $open->clone()->whereNotNull('due_date')->where('due_date', '<', $this->context->today());

        return [
            'count' => $open->clone()->count(),
            'total_label' => Money::format($this->outstanding($open)),
            'overdue_count' => $overdue->clone()->count(),
            'overdue_label' => Money::format($this->outstanding($overdue)),
        ];
    }

    /**
     * Supplier bills due within 3 days or already overdue.
     *
     * @return array{count: int, total_label: string}
     */
    public function supplierDue(): array
    {
        $due = Purchase::query()
            ->whereIn('status', [PurchaseStatus::Unpaid, PurchaseStatus::Partial])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', Carbon::parse($this->context->today())->addDays(3)->toDateString());

        return ['count' => $due->clone()->count(), 'total_label' => Money::format($this->outstanding($due))];
    }

    /**
     * Balances of the accounts this user may see (cash for Front Desk, all for Finance).
     *
     * @return list<array{id: int, name: string, is_physical: bool, balance: int, balance_label: string}>
     */
    public function cash(User $user): array
    {
        $accounts = CashAccount::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->get()
            ->filter(fn (CashAccount $account) => $user->can('view', $account))
            ->values();
        $balances = $this->cash->balances($accounts);

        return $accounts->map(fn (CashAccount $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'is_physical' => $account->type->isPhysical(),
            'balance' => $balances[$account->id] ?? 0,
            'balance_label' => Money::format($balances[$account->id] ?? 0),
        ])->all();
    }

    /**
     * Money figures for the period (dashboard.view_financials).
     *
     * @return array<string, mixed>
     */
    public function financials(User $user, string $from, string $to): array
    {
        $invoices = Invoice::query()->where('status', '!=', InvoiceStatus::Void)->whereBetween('issue_date', [$from, $to]);
        $sales = (int) $invoices->clone()->sum('total') - (int) $invoices->clone()->sum('tax_amount');
        $tax = (int) $invoices->clone()->sum('tax_amount');

        $payments = Payment::query()
            ->whereNull('voided_at')
            ->whereHas('receipt', fn (Builder $receipt) => $receipt->whereBetween('paid_on', [$from, $to]));

        // Expenses this user may see (salaries only with expense.view_restricted).
        $expenses = $this->expenses($from, $to)
            ->when(! $user->can(Permission::ExpenseViewRestricted->value), fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('is_restricted', false)));

        $result = [
            'sales_label' => Money::format($sales),
            'invoice_count' => $invoices->clone()->count(),
            'tax_label' => $tax > 0 ? Money::format($tax) : null,
            'received_label' => Money::format((int) $payments->clone()->sum('amount')),
            'surcharge_label' => Money::format((int) $payments->clone()->sum('surcharge_amount')),
            'expenses_label' => Money::format((int) $expenses->sum('amount')),
            'expenses_include_restricted' => $user->can(Permission::ExpenseViewRestricted->value),
            'profit' => null,
        ];

        if ($user->can(Permission::DashboardViewProfit->value)) {
            $partsCost = $this->partsCost($from, $to);
            $allExpenses = (int) $this->expenses($from, $to)->sum('amount');
            $result['profit'] = [
                'parts_cost_label' => Money::format($partsCost),
                'expenses_label' => Money::format($allExpenses),
                'profit' => $sales - $partsCost - $allExpenses,
                'profit_label' => Money::format($sales - $partsCost - $allExpenses),
            ];
        }

        return $result;
    }

    /**
     * Sales (without tax) per month for the last 12 months, oldest first.
     *
     * @return list<array{month: string, sales: int}>
     */
    public function monthlySales(): array
    {
        $end = Carbon::parse($this->context->today())->endOfMonth();
        $start = $end->copy()->subMonthsNoOverflow(11)->startOfMonth();

        $rows = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Void)
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(issue_date, '%Y-%m') as month, SUM(total - tax_amount) as sales")
            ->groupBy('month')
            ->pluck('sales', 'month');

        $months = [];
        for ($month = $start->copy(); $month <= $end; $month->addMonthNoOverflow()) {
            $key = $month->format('Y-m');
            $months[] = ['month' => $key, 'sales' => (int) ($rows[$key] ?? 0)];
        }

        return $months;
    }

    /**
     * The five services and parts that brought in the most in the period.
     *
     * @return array{services: list<array<string, mixed>>, parts: list<array<string, mixed>>}
     */
    public function topItems(string $from, string $to): array
    {
        $lines = fn () => InvoiceLine::query()
            ->whereHas('invoice', fn (Builder $invoice) => $invoice->where('status', '!=', InvoiceStatus::Void)->whereBetween('issue_date', [$from, $to]));

        $services = $lines()->where('type', 'service')
            ->selectRaw('description, COUNT(*) as times, SUM(line_total) as revenue')
            ->groupBy('description')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->getAttribute('description'), 'count' => (int) $row->getAttribute('times'), 'revenue_label' => Money::format((int) $row->getAttribute('revenue'))])
            ->all();

        $parts = $lines()->where('type', 'part')
            ->selectRaw('description, SUM(qty) as quantity, SUM(line_total) as revenue')
            ->groupBy('description')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->getAttribute('description'), 'qty_label' => Quantity::format(Quantity::fromDecimal((string) $row->getAttribute('quantity'))), 'revenue_label' => Money::format((int) $row->getAttribute('revenue'))])
            ->all();

        return ['services' => $services, 'parts' => $parts];
    }

    /**
     * Work orders each mechanic finished in the period.
     *
     * @return list<array{name: string, jobs: int}>
     */
    public function mechanicWorkload(string $from, string $to): array
    {
        $timezone = $this->context->timezone();
        $start = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($to, $timezone)->endOfDay()->utc();

        $finished = WorkOrder::query()
            ->whereIn('status', [WorkOrderStatus::Completed, WorkOrderStatus::Invoiced, WorkOrderStatus::Closed])
            ->whereBetween('completed_at', [$start, $end])
            ->pluck('id');

        if ($finished->isEmpty()) {
            return [];
        }

        return DB::table('work_order_mechanic')
            ->join('mechanics', 'mechanics.id', '=', 'work_order_mechanic.mechanic_id')
            ->whereIn('work_order_mechanic.work_order_id', $finished)
            ->selectRaw('mechanics.name as name, COUNT(*) as jobs')
            ->groupBy('mechanics.id', 'mechanics.name')
            ->orderByDesc('jobs')
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'jobs' => (int) $row->jobs])
            ->all();
    }

    /**
     * Cost of the parts on the invoices of the period: Σ round(qty × unit cost).
     */
    private function partsCost(string $from, string $to): int
    {
        return (int) InvoiceLine::query()
            ->where('type', 'part')
            ->whereHas('invoice', fn (Builder $invoice) => $invoice->where('status', '!=', InvoiceStatus::Void)->whereBetween('issue_date', [$from, $to]))
            ->sum(DB::raw('ROUND(qty * COALESCE(unit_cost, 0))'));
    }

    /**
     * @return Builder<Expense>
     */
    private function expenses(string $from, string $to): Builder
    {
        return Expense::query()->whereNull('voided_at')->whereBetween('expense_date', [$from, $to]);
    }

    /**
     * @param  Builder<Invoice>|Builder<Purchase>  $query
     */
    private function outstanding(Builder $query): int
    {
        return (int) $query->clone()->sum('total') - (int) $query->clone()->sum('amount_paid');
    }
}
