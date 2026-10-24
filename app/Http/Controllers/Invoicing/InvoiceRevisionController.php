<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceRevision;
use App\Models\ServiceItem;
use App\Models\User;
use App\Services\Invoicing\InvoiceService;
use App\Services\Invoicing\RevisionComparer;
use App\Support\BranchTime;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editing an issued invoice with a reason, and its revision history (SPEC section 6).
 */
class InvoiceRevisionController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function edit(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        /** @var User $user */
        $user = $request->user();
        $invoice->load(['customer', 'workOrder.vehicle', 'lines.part']);

        return Inertia::render('Invoices/Edit', [
            'invoice' => InvoiceResource::make($invoice)->resolve($request),
            'catalog' => ServiceItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_price']),
            'tax' => [
                'enabled' => $invoice->tax_enabled,
                'name' => $invoice->tax_name,
                'rate' => $invoice->tax_rate,
                'inclusive' => $invoice->tax_inclusive,
            ],
            'amountPaid' => $invoice->amount_paid,
            'can' => [
                'discount' => $user->can('discount', Invoice::class),
                'toggleTax' => $user->can('toggleTax', Invoice::class),
            ],
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->revise($invoice, $request->invoiceHeader(), $request->lines(), $request->string('reason')->toString());

        return redirect()->route('invoices.show', $invoice)->with('success', __('invoices.revised', ['number' => $invoice->number]));
    }

    /**
     * Every earlier version with what changed in the next one, newest first.
     */
    public function index(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['lines.part']);
        $revisions = InvoiceRevision::query()->with('editor')->where('invoice_id', $invoice->id)->orderBy('revision_no')->get()->values();
        $current = $this->invoices->snapshot($invoice);

        $history = $revisions->map(function (InvoiceRevision $revision, int $index) use ($revisions, $current) {
            $next = $index + 1 < $revisions->count() ? $revisions[$index + 1]->snapshot : $current;

            return [
                'id' => $revision->id,
                // The edit that produced version revision_no + 1.
                'version' => $revision->revision_no + 1,
                'reason' => $revision->reason,
                'edited_by' => $revision->editor?->name,
                'edited_at_label' => BranchTime::label($revision->created_at),
                'total_before' => Money::format((int) $revision->snapshot['total']),
                'total_after' => Money::format((int) $next['total']),
                'changes' => RevisionComparer::compare($revision->snapshot, $next),
            ];
        })->reverse()->values()->all();

        return Inertia::render('Invoices/Revisions', [
            'invoice' => ['id' => $invoice->id, 'number' => $invoice->number, 'revision_no' => $invoice->revision_no],
            'history' => $history,
        ]);
    }
}
