<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PaySupplierRequest;
use App\Http\Requests\Purchasing\ReasonRequest;
use App\Models\CashAccount;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Services\Purchasing\SupplierPaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class SupplierPaymentController extends Controller
{
    public function __construct(private readonly SupplierPaymentService $payments) {}

    public function store(PaySupplierRequest $request, Purchase $purchase): RedirectResponse
    {
        /** @var CashAccount $account */
        $account = $request->account();
        $amount = $request->integer('amount');

        $this->payments->pay(
            $purchase,
            $amount,
            $account,
            Carbon::parse($request->string('paid_on')->toString()),
            $request->input('reference'),
            $request->input('note'),
        );

        return back()->with('success', __('purchases.paid_message', ['amount' => Money::format($amount), 'number' => $purchase->number]));
    }

    public function void(ReasonRequest $request, SupplierPayment $supplierPayment): RedirectResponse
    {
        $this->payments->void($supplierPayment, $request->string('reason')->toString());

        return back()->with('success', __('purchases.payment_voided'));
    }
}
