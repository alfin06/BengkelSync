<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Support\BranchTime;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lists need customer and workOrder.vehicle loaded; the detail page also loads lines.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vehicle = $this->workOrder?->vehicle;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'customer' => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone === null ? null : Phone::format($this->customer->phone),
                'address' => $this->customer->address,
            ],
            'work_order' => $this->workOrder === null ? null : [
                'id' => $this->workOrder->id,
                'number' => $this->workOrder->number,
                'odometer_in' => $this->workOrder->odometer_in,
            ],
            'vehicle' => $vehicle === null ? null : [
                'id' => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'name' => $vehicle->displayName(),
            ],
            'subtotal_label' => Money::format($this->subtotal),
            'discount_amount' => $this->discount_amount,
            'discount_label' => Money::format($this->discount_amount),
            'tax_enabled' => $this->tax_enabled,
            'tax_name' => $this->tax_name,
            'tax_rate_label' => rtrim(rtrim($this->tax_rate, '0'), '.').'%',
            'tax_inclusive' => $this->tax_inclusive,
            'tax_label' => Money::format($this->tax_amount),
            'total' => $this->total,
            'total_label' => Money::format($this->total),
            'amount_paid_label' => Money::format($this->amount_paid),
            'outstanding' => $this->outstanding(),
            'outstanding_label' => Money::format($this->outstanding()),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'void_reason' => $this->void_reason,
            'revision_no' => $this->revision_no,
            'notes' => $this->notes,
            'created_at_label' => BranchTime::label($this->created_at),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (InvoiceLine $line) => [
                'id' => $line->id,
                'type' => $line->type->value,
                'description' => $line->description,
                'qty_label' => Quantity::format($line->qtyHundredths()),
                'unit_label' => $line->part?->unit->label(),
                'unit_price_label' => Money::format($line->unit_price),
                'discount_amount' => $line->discount_amount,
                'discount_label' => Money::format($line->discount_amount),
                'line_total_label' => Money::format($line->line_total),
            ])->all()),
        ];
    }
}
