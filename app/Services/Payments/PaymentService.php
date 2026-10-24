<?php

namespace App\Services\Payments;

use App\Enums\CashMovementType;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethodType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Services\Audit\AuditLogger;
use App\Services\Cash\CashService;
use App\Services\Invoicing\InvoiceSettlement;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Customer payments (SPEC section 4). A checkout can use several methods at once and an
 * invoice can be paid over several checkouts, each with any methods. Every line puts its
 * money into its method's cash account through CashService, in the same transaction that
 * updates the invoice. Wrong lines are voided (never deleted) with a reversing movement.
 */
class PaymentService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CashService $cash,
        private readonly InvoiceSettlement $settlement,
        private readonly AuditLogger $audit,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  list<array{method: PaymentMethod, amount: int, cash_tendered?: int|null, reference?: string|null}>  $lines
     * @param  string|null  $dueDate  required when the invoice is not fully paid afterwards
     */
    public function checkout(Invoice $invoice, string $paidOn, array $lines, ?string $dueDate = null, ?string $note = null): PaymentReceipt
    {
        if ($lines === []) {
            throw new BusinessRuleViolation(__('payments.no_lines'));
        }

        return DB::transaction(function () use ($invoice, $paidOn, $lines, $dueDate, $note): PaymentReceipt {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvoiceStatus::Void) {
                throw new BusinessRuleViolation(__('invoices.is_void'));
            }

            $total = 0;
            $prepared = [];
            foreach ($lines as $line) {
                $prepared[] = $this->prepareLine($line);
                $total += $line['amount'];
            }

            if ($total > $locked->outstanding()) {
                throw new BusinessRuleViolation(__('payments.overpayment', ['outstanding' => Money::format($locked->outstanding())]));
            }

            $remaining = $locked->outstanding() - $total;
            if ($remaining > 0) {
                $this->setDueDate($locked, $paidOn, $dueDate);
            }

            $receipt = new PaymentReceipt(['invoice_id' => $locked->id, 'paid_on' => $paidOn, 'note' => $this->nullIfBlank($note)]);
            $receipt->forceFill([
                'number' => DocumentNumber::next($this->context->requireBranch(), DocumentType::Receipt, Carbon::parse($paidOn)),
                'amount' => $total,
                'surcharge_total' => array_sum(array_column($prepared, 'surcharge_amount')),
                'received_by' => $this->userId(),
            ])->save();

            foreach ($prepared as $line) {
                /** @var PaymentMethod $method */
                $method = $line['method'];
                $payment = Payment::query()->create([
                    'receipt_id' => $receipt->id,
                    'invoice_id' => $locked->id,
                    'payment_method_id' => $method->id,
                    'cash_account_id' => $method->cash_account_id,
                    'amount' => $line['amount'],
                    'surcharge_percent' => $method->surcharge_percent,
                    'surcharge_amount' => $line['surcharge_amount'],
                    'cash_tendered' => $line['cash_tendered'],
                    'reference' => $line['reference'],
                ]);

                $this->cash->record($method->account, CashMovementType::CustomerPayment, $payment->received(), $payment, __('payments.cash_note', ['invoice' => $locked->number, 'receipt' => $receipt->number]));
            }

            $this->settlement->apply($locked, $locked->amount_paid + $total);

            return $receipt;
        });
    }

    /**
     * Cancel one line of a kwitansi: its money leaves the cash account again and the
     * invoice owes that amount again. The line stays, marked as cancelled.
     */
    public function voidPayment(Payment $payment, string $reason): void
    {
        $this->ensureReason($reason);

        DB::transaction(function () use ($payment, $reason): void {
            $invoice = Invoice::query()->whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $this->voidLocked(Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail(), $invoice, trim($reason));
        });

        $payment->refresh();
    }

    /**
     * Cancel every line of a kwitansi that is not cancelled yet.
     */
    public function voidReceipt(PaymentReceipt $receipt, string $reason): void
    {
        $this->ensureReason($reason);

        DB::transaction(function () use ($receipt, $reason): void {
            $invoice = Invoice::query()->whereKey($receipt->invoice_id)->lockForUpdate()->firstOrFail();
            $open = Payment::query()->where('receipt_id', $receipt->id)->whereNull('voided_at')->lockForUpdate()->get();

            if ($open->isEmpty()) {
                throw new BusinessRuleViolation(__('payments.already_voided'));
            }

            foreach ($open as $payment) {
                $this->voidLocked($payment, $invoice, trim($reason));
            }
        });
    }

    private function voidLocked(Payment $payment, Invoice $invoice, string $reason): void
    {
        if ($payment->isVoided()) {
            throw new BusinessRuleViolation(__('payments.already_voided'));
        }

        $payment->forceFill(['voided_at' => now(), 'void_reason' => $reason, 'voided_by' => $this->userId()])->save();

        $this->cash->record($payment->account, CashMovementType::CustomerPayment, -$payment->received(), $payment, __('payments.void_cash_note', ['invoice' => $invoice->number, 'reason' => $reason]));
        $this->settlement->apply($invoice, $invoice->amount_paid - $payment->amount);
        $this->audit->record('payment_voided', $payment, ['amount' => $payment->amount, 'surcharge_amount' => $payment->surcharge_amount], [], $reason);
    }

    /**
     * @param  array{method: PaymentMethod, amount: int, cash_tendered?: int|null, reference?: string|null}  $line
     * @return array{method: PaymentMethod, amount: int, surcharge_amount: int, cash_tendered: int|null, reference: string|null}
     */
    private function prepareLine(array $line): array
    {
        $method = $line['method'];
        $amount = $line['amount'];

        if (! $method->is_active) {
            throw new BusinessRuleViolation(__('payments.method_inactive', ['method' => $method->name]));
        }

        if ($amount <= 0) {
            throw new BusinessRuleViolation(__('cash.zero_amount'));
        }

        $surcharge = Money::percentOf($amount, $method->surcharge_percent);
        $reference = $this->nullIfBlank($line['reference'] ?? null);

        if ($method->needs_reference && $reference === null) {
            throw new BusinessRuleViolation(__('payments.reference_required', ['method' => $method->name]));
        }

        // Cash: the money handed over may be more than the amount; the rest is change.
        $tendered = $method->type === PaymentMethodType::Cash ? ($line['cash_tendered'] ?? null) : null;
        if ($tendered !== null && $tendered < $amount + $surcharge) {
            throw new BusinessRuleViolation(__('payments.tendered_too_low', ['amount' => Money::format($amount + $surcharge)]));
        }

        return ['method' => $method, 'amount' => $amount, 'surcharge_amount' => $surcharge, 'cash_tendered' => $tendered, 'reference' => $reference];
    }

    /**
     * Part of the invoice stays unpaid: it needs a due date (pilot decision). A new date
     * gets its own reminders.
     */
    private function setDueDate(Invoice $invoice, string $paidOn, ?string $dueDate): void
    {
        if ($dueDate === null || trim($dueDate) === '') {
            throw new BusinessRuleViolation(__('payments.due_date_required'));
        }

        if ($dueDate < $paidOn) {
            throw new BusinessRuleViolation(__('payments.due_date_before_payment'));
        }

        if ($invoice->due_date?->toDateString() !== $dueDate) {
            $invoice->forceFill(['due_date' => $dueDate, 'due_soon_notified_at' => null, 'overdue_notified_at' => null]);
        }
    }

    private function ensureReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function userId(): ?int
    {
        $id = $this->auth->guard()->id();

        return is_int($id) ? $id : null;
    }
}
