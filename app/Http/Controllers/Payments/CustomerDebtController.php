<?php

namespace App\Http\Controllers\Payments;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Piutang pelanggan": what customers still owe this branch, nearest due date first.
 */
class CustomerDebtController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewDebts', Invoice::class);

        $open = Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
        $outstanding = (int) $open->clone()->sum('total') - (int) $open->clone()->sum('amount_paid');
        $overdue = $open->clone()->whereNotNull('due_date')->where('due_date', '<', $context->today());

        return Inertia::render('Payments/Debts', [
            'debts' => $open->clone()
                ->with(['customer', 'workOrder.vehicle'])
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->orderBy('issue_date')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Invoice $invoice) => InvoiceResource::make($invoice)->resolve($request)),
            'outstandingLabel' => Money::format($outstanding),
            'overdueLabel' => Money::format((int) $overdue->clone()->sum('total') - (int) $overdue->clone()->sum('amount_paid')),
            'today' => $context->today(),
        ]);
    }
}
