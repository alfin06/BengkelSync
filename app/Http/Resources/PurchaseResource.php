<?php

namespace App\Http\Resources;

use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\SupplierPayment;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Purchase
 */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = app(TenantContext::class)->today();
        $due = $this->due_date?->toDateString();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier' => ['id' => $this->supplier->id, 'name' => $this->supplier->name],
            'supplier_ref' => $this->supplier_ref,
            'purchase_date' => $this->purchase_date->toDateString(),
            'due_date' => $due,
            'is_overdue' => $due !== null && $due < $today && $this->outstanding() > 0,
            'total' => $this->total,
            'total_label' => Money::format($this->total),
            'amount_paid_label' => Money::format($this->amount_paid),
            'outstanding' => $this->outstanding(),
            'outstanding_label' => Money::format($this->outstanding()),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'void_reason' => $this->void_reason,
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (PurchaseLine $line) => [
                'id' => $line->id,
                'part_id' => $line->part_id,
                'part_name' => $line->part->name,
                'sku' => $line->part->sku,
                'unit_label' => $line->part->unit->label(),
                'allows_decimals' => $line->part->unit->allowsDecimals(),
                // "4.2" for inputs, "4,2" for display.
                'qty' => rtrim(rtrim($line->qty, '0'), '.'),
                'qty_label' => Quantity::format($line->qtyHundredths()),
                'unit_cost' => $line->unit_cost,
                'unit_cost_label' => Money::format($line->unit_cost),
                'line_total_label' => Money::format($line->line_total),
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (SupplierPayment $payment) => [
                'id' => $payment->id,
                'paid_on' => $payment->paid_on->toDateString(),
                'amount_label' => Money::format($payment->amount),
                'account_name' => $payment->account->name,
                'reference' => $payment->reference,
                'note' => $payment->note,
                'payer' => $payment->payer?->name,
                'voided' => $payment->isVoided(),
                'void_reason' => $payment->void_reason,
                'can_void' => $request->user()?->can('void', $payment) ?? false,
            ])->all()),
        ];
    }
}
