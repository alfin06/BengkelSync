<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CheckoutRequest;
use App\Http\Requests\Payments\VoidPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\Payments\PaymentService;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Terbilang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(CheckoutRequest $request, Invoice $invoice): RedirectResponse
    {
        $receipt = $this->payments->checkout(
            $invoice,
            $request->string('paid_on')->toString(),
            $request->lines(),
            $request->filled('due_date') ? $request->string('due_date')->toString() : null,
            $request->input('note'),
        );

        return back()->with('success', __('payments.saved', ['number' => $receipt->number, 'amount' => Money::format($receipt->amount)]));
    }

    public function voidPayment(VoidPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->payments->voidPayment($payment, $request->string('reason')->toString());

        return back()->with('success', __('payments.line_voided'));
    }

    public function voidReceipt(VoidPaymentRequest $request, PaymentReceipt $receipt): RedirectResponse
    {
        $this->payments->voidReceipt($receipt, $request->string('reason')->toString());

        return back()->with('success', __('payments.receipt_voided', ['number' => $receipt->number]));
    }

    /**
     * The kwitansi on A5, like the invoice. Cancelled lines are printed as cancelled.
     */
    public function print(Request $request, PaymentReceipt $receipt): Response
    {
        Gate::authorize('view', $receipt);

        $receipt->load(['invoice.customer', 'invoice.workOrder.vehicle', 'payments.method', 'receiver', 'branch']);
        $invoice = $receipt->invoice;
        $active = $receipt->activeAmount();

        return Inertia::render('Payments/Receipt', [
            'receipt' => [
                'number' => $receipt->number,
                'paid_on' => $receipt->paid_on->toDateString(),
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'customer' => $invoice->customer?->name,
                'customer_phone' => $invoice->customer?->phone === null ? null : Phone::format($invoice->customer->phone),
                'vehicle' => $invoice->workOrder?->vehicle->plate_number,
                'amount_label' => Money::format($active),
                'amount_words' => Terbilang::rupiah($active),
                'invoice_total_label' => Money::format($invoice->total),
                'outstanding_label' => Money::format($invoice->outstanding()),
                'due_date' => $invoice->outstanding() > 0 ? $invoice->due_date?->toDateString() : null,
                'received_by' => $receipt->receiver?->name,
                'note' => $receipt->note,
                'lines' => $receipt->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'method' => $payment->method->name,
                    'amount_label' => Money::format($payment->amount),
                    'surcharge_label' => $payment->surcharge_amount > 0 ? Money::format($payment->surcharge_amount) : null,
                    'reference' => $payment->reference,
                    'change_label' => $payment->cash_tendered !== null && $payment->cash_tendered > $payment->received()
                        ? Money::format($payment->cash_tendered - $payment->received())
                        : null,
                    'voided' => $payment->isVoided(),
                ])->all(),
            ],
            'branch' => [
                'name' => $receipt->branch->name,
                'address' => $receipt->branch->address,
                'phone' => $receipt->branch->phone,
            ],
        ]);
    }
}
