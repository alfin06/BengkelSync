<?php

namespace App\Services\Reports;

use App\Enums\CashMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\WorkOrderStatus;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * "Rekap harian": one day of the active branch, for checking the cash drawer in the
 * evening and for the Owner's daily look at the shop (report.view).
 *
 * Sales and money received use the dashboard's definitions (DashboardService): sales are
 * the day's invoices (not cancelled) without tax; money received is the day's payments
 * (not cancelled) without card fees. Cash per account comes from the cash ledger, so the
 * closing balance always matches the Kas page. Every query goes through the models, so
 * tenant and branch scopes apply.
 */
class DailySummaryService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, string $date): array
    {
        $timezone = $this->context->timezone();
        $start = Carbon::parse($date, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($date, $timezone)->endOfDay()->utc();
        $seesRestricted = $user->can(Permission::ExpenseViewRestricted->value);

        return [
            'date' => $date,
            'sales' => $this->sales($date),
            'received' => $this->received($date),
            'expenses' => $this->expenses($date, $seesRestricted),
            'supplier_payments' => $this->supplierPayments($date),
            'transfers' => $this->transfers($start, $end),
            'cash' => $this->cash($user, $start, $end),
            'workshop' => [
                'checked_in' => WorkOrder::query()->whereBetween('created_at', [$start, $end])->count(),
                'completed' => WorkOrder::query()
                    ->whereIn('status', [WorkOrderStatus::Completed, WorkOrderStatus::Invoiced, WorkOrderStatus::Closed])
                    ->whereBetween('completed_at', [$start, $end])
                    ->count(),
            ],
            'cancellations' => $this->cancellations($start, $end),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sales(string $date): array
    {
        $invoices = Invoice::query()->with('customer')->where('status', '!=', InvoiceStatus::Void)->where('issue_date', $date)->orderBy('id')->get();
        $lineDiscounts = (int) InvoiceLine::query()->whereIn('invoice_id', $invoices->modelKeys())->sum('discount_amount');
        $tax = (int) $invoices->sum('tax_amount');
        $total = (int) $invoices->sum('total');
        $unpaid = $invoices->filter(fn (Invoice $invoice) => in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true));
        $outstanding = (int) $unpaid->sum(fn (Invoice $invoice) => $invoice->total - $invoice->amount_paid);

        return [
            'count' => $invoices->count(),
            'sales_label' => Money::format($total - $tax),
            'discount_label' => Money::format($lineDiscounts + (int) $invoices->sum('discount_amount')),
            'tax_label' => $tax > 0 ? Money::format($tax) : null,
            'total_label' => Money::format($total),
            'unpaid_count' => $unpaid->count(),
            'unpaid_label' => Money::format($outstanding),
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer' => $invoice->customer?->name,
                'total_label' => Money::format($invoice->total),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
            ])->all(),
        ];
    }

    /**
     * Payments received on the day, per payment method.
     *
     * @return array<string, mixed>
     */
    private function received(string $date): array
    {
        $payments = Payment::query()
            ->with('method')
            ->whereNull('voided_at')
            ->whereHas('receipt', fn (Builder $receipt) => $receipt->where('paid_on', $date))
            ->get();

        $methods = $payments->groupBy('payment_method_id')->map(fn ($group) => [
            'name' => $group->first()?->method->name ?? '',
            'count' => $group->count(),
            'amount_label' => Money::format((int) $group->sum('amount')),
            'surcharge_label' => (int) $group->sum('surcharge_amount') > 0 ? Money::format((int) $group->sum('surcharge_amount')) : null,
        ])->sortBy('name')->values()->all();

        return [
            'total_label' => Money::format((int) $payments->sum('amount')),
            'surcharge_label' => Money::format((int) $payments->sum('surcharge_amount')),
            'methods' => $methods,
        ];
    }

    /**
     * Expenses of the day; restricted categories (salaries) only with expense.view_restricted.
     *
     * @return array<string, mixed>
     */
    private function expenses(string $date, bool $seesRestricted): array
    {
        $expenses = Expense::query()
            ->with(['category', 'account'])
            ->whereNull('voided_at')
            ->where('expense_date', $date)
            ->when(! $seesRestricted, fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('is_restricted', false)))
            ->orderBy('id')
            ->get();

        return [
            'total_label' => Money::format((int) $expenses->sum('amount')),
            'includes_restricted' => $seesRestricted,
            'items' => $expenses->map(fn (Expense $expense) => [
                'category' => $expense->category->name,
                'description' => $expense->description,
                'account' => $expense->account->name,
                'amount_label' => Money::format($expense->amount),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPayments(string $date): array
    {
        $payments = SupplierPayment::query()
            ->with(['purchase.supplier', 'account'])
            ->whereNull('voided_at')
            ->where('paid_on', $date)
            ->orderBy('id')
            ->get();

        return [
            'total_label' => Money::format((int) $payments->sum('amount')),
            'items' => $payments->map(fn (SupplierPayment $payment) => [
                'supplier' => $payment->purchase->supplier->name,
                'purchase' => $payment->purchase->number,
                'account' => $payment->account->name,
                'amount_label' => Money::format($payment->amount),
            ])->all(),
        ];
    }

    /**
     * Money moved between the branch's own accounts on the day.
     *
     * @return list<array{from: string, to: string, amount_label: string, note: string|null}>
     */
    private function transfers(Carbon $start, Carbon $end): array
    {
        $movements = CashMovement::query()
            ->with('account')
            ->whereIn('type', [CashMovementType::TransferOut, CashMovementType::TransferIn])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id')
            ->get()
            ->groupBy('transfer_group');

        return $movements->map(function ($pair) {
            $out = $pair->first(fn (CashMovement $movement) => $movement->type === CashMovementType::TransferOut);
            $in = $pair->first(fn (CashMovement $movement) => $movement->type === CashMovementType::TransferIn);
            $side = $out ?? $in;

            return [
                'from' => $out === null ? '' : $out->account->name,
                'to' => $in === null ? '' : $in->account->name,
                'amount_label' => Money::format($side === null ? 0 : abs($side->amount)),
                'note' => $side?->note,
            ];
        })->values()->all();
    }

    /**
     * Opening balance, money in, money out and closing balance of each account this user
     * may see, from the cash ledger. For the cash drawer, the closing balance is what
     * should be in the drawer that evening.
     *
     * @return list<array<string, mixed>>
     */
    private function cash(User $user, Carbon $start, Carbon $end): array
    {
        $accounts = CashAccount::query()->orderBy('sort')->orderBy('name')->get()
            ->filter(fn (CashAccount $account) => $user->can('view', $account))
            ->values();

        return $accounts->map(function (CashAccount $account) use ($start, $end): ?array {
            $movements = CashMovement::query()->where('cash_account_id', $account->id);
            $opening = (int) ($movements->clone()->where('created_at', '<', $start)->latest('id')->value('balance_after') ?? 0);
            $day = $movements->clone()->whereBetween('created_at', [$start, $end]);
            $in = (int) $day->clone()->where('amount', '>', 0)->sum('amount');
            $out = -(int) $day->clone()->where('amount', '<', 0)->sum('amount');
            $closing = (int) ($movements->clone()->where('created_at', '<=', $end)->latest('id')->value('balance_after') ?? 0);

            // Inactive accounts only show up on days they moved or still hold money.
            if (! $account->is_active && $in === 0 && $out === 0 && $closing === 0) {
                return null;
            }

            return [
                'name' => $account->name,
                'is_physical' => $account->type->isPhysical(),
                'opening_label' => Money::format($opening),
                'in_label' => Money::format($in),
                'out_label' => Money::format($out),
                'closing_label' => Money::format($closing),
            ];
        })->filter()->values()->all();
    }

    /**
     * Invoices and customer payments cancelled on the day, with the reason, so the Owner
     * sees every correction.
     *
     * @return list<array{kind: string, number: string, amount_label: string, reason: string|null}>
     */
    private function cancellations(Carbon $start, Carbon $end): array
    {
        $invoices = Invoice::query()->where('status', InvoiceStatus::Void)->whereBetween('voided_at', [$start, $end])->orderBy('voided_at')->get()
            ->map(fn (Invoice $invoice) => [
                'kind' => 'invoice',
                'number' => $invoice->number,
                'amount_label' => Money::format($invoice->total),
                'reason' => $invoice->void_reason,
            ]);

        $payments = Payment::query()->with(['receipt', 'method'])->whereBetween('voided_at', [$start, $end])->orderBy('voided_at')->get()
            ->map(fn (Payment $payment) => [
                'kind' => 'payment',
                'number' => $payment->receipt->number.' · '.$payment->method->name,
                'amount_label' => Money::format($payment->amount),
                'reason' => $payment->void_reason,
            ]);

        return [...$invoices->all(), ...$payments->all()];
    }
}
