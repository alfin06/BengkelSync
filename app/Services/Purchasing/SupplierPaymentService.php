<?php

namespace App\Services\Purchasing;

use App\Enums\CashMovementType;
use App\Enums\PurchaseStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CashAccount;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Services\Audit\AuditLogger;
use App\Services\Cash\CashService;
use App\Support\Money;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paying suppliers. Each payment takes money out of a cash account through CashService
 * and updates the purchase's paid amount and status in the same transaction.
 */
class SupplierPaymentService
{
    public function __construct(
        private readonly CashService $cash,
        private readonly AuditLogger $audit,
        private readonly AuthFactory $auth,
    ) {}

    public function pay(
        Purchase $purchase,
        int $amount,
        CashAccount $account,
        ?Carbon $paidOn = null,
        ?string $reference = null,
        ?string $note = null,
    ): SupplierPayment {
        if ($amount <= 0) {
            throw new BusinessRuleViolation(__('cash.zero_amount'));
        }

        return DB::transaction(function () use ($purchase, $amount, $account, $paidOn, $reference, $note): SupplierPayment {
            // Lock the purchase so two simultaneous payments cannot both see the old balance.
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseStatus::Void) {
                throw new BusinessRuleViolation(__('purchases.is_void'));
            }

            if ($amount > $locked->outstanding()) {
                throw new BusinessRuleViolation(__('purchases.overpayment', ['outstanding' => Money::format($locked->outstanding())]));
            }

            $payment = SupplierPayment::query()->create([
                'purchase_id' => $locked->id,
                'cash_account_id' => $account->id,
                'paid_on' => ($paidOn ?? now())->toDateString(),
                'amount' => $amount,
                'reference' => $reference,
                'note' => $note,
                'paid_by' => $this->userId(),
            ]);

            $this->cash->record($account, CashMovementType::SupplierPayment, -$amount, $payment, __('purchases.payment_note', ['number' => $locked->number]));
            $this->applyPaid($locked, $locked->amount_paid + $amount);
            $purchase->refresh();

            return $payment;
        });
    }

    /**
     * Cancel a wrong payment: the money goes back into the account with a new movement,
     * and the payment row is kept and marked as voided (SPEC section 6).
     */
    public function void(SupplierPayment $payment, string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        DB::transaction(function () use ($payment, $reason): void {
            $locked = SupplierPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new BusinessRuleViolation(__('purchases.payment_already_voided'));
            }

            $purchase = Purchase::query()->whereKey($locked->purchase_id)->lockForUpdate()->firstOrFail();
            $account = CashAccount::query()->findOrFail($locked->cash_account_id);

            $locked->forceFill(['voided_at' => now(), 'void_reason' => trim($reason), 'voided_by' => $this->userId()])->save();
            $this->cash->record($account, CashMovementType::SupplierPayment, $locked->amount, $locked, __('purchases.payment_void_note', ['number' => $purchase->number, 'reason' => trim($reason)]));
            $this->applyPaid($purchase, $purchase->amount_paid - $locked->amount);
            $this->audit->record('supplier_payment_voided', $locked, ['amount' => $locked->amount], [], trim($reason));
        });

        $payment->refresh();
    }

    private function applyPaid(Purchase $purchase, int $paid): void
    {
        $purchase->forceFill([
            'amount_paid' => $paid,
            'status' => PurchaseStatus::for($purchase->total, $paid),
        ])->save();
    }

    private function userId(): ?int
    {
        $id = $this->auth->guard()->id();

        return is_int($id) ? $id : null;
    }
}
