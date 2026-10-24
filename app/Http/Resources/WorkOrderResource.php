<?php

namespace App\Http\Resources;

use App\Models\Mechanic;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Models\WorkOrderServiceLine;
use App\Models\WorkOrderTask;
use App\Support\BranchTime;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List rows need vehicle and customer loaded; the detail page also loads tasks,
 * services, parts and mechanics.
 *
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->relationLoaded('services') && $this->relationLoaded('parts');

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'complaint' => $this->complaint,
            'diagnosis' => $this->diagnosis,
            'odometer_in' => $this->odometer_in,
            'estimated_completion' => BranchTime::toInput($this->estimated_completion),
            'estimated_completion_label' => BranchTime::label($this->estimated_completion),
            'started_at_label' => BranchTime::label($this->started_at),
            'completed_at_label' => BranchTime::label($this->completed_at),
            'created_at_label' => BranchTime::label($this->created_at),
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'vehicle' => [
                'id' => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'name' => $this->vehicle->displayName(),
                'last_odometer' => $this->vehicle->last_odometer,
            ],
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone === null ? null : Phone::format($this->customer->phone),
            ],
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn (WorkOrderTask $task) => [
                'id' => $task->id,
                'description' => $task->description,
                'is_done' => $task->is_done,
            ])->all()),
            'mechanics' => $this->whenLoaded('mechanics', fn () => $this->mechanics->map(fn (Mechanic $mechanic) => [
                'id' => $mechanic->id,
                'name' => $mechanic->name,
            ])->all()),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn (WorkOrderServiceLine $line) => [
                'id' => $line->id,
                'service_item_id' => $line->service_item_id,
                'description' => $line->description,
                'qty' => $line->qty,
                'unit_price' => $line->unit_price,
                'unit_price_label' => Money::format($line->unit_price),
                'line_total_label' => Money::format($line->line_total),
                'mechanic_id' => $line->mechanic_id,
                'mechanic_name' => $line->mechanic?->name,
            ])->all()),
            'parts' => $this->whenLoaded('parts', fn () => $this->parts->map(fn (WorkOrderPart $line) => [
                'id' => $line->id,
                'part_id' => $line->part_id,
                'part_name' => $line->part->name,
                'sku' => $line->part->sku,
                'unit_label' => $line->part->unit->label(),
                'allows_decimals' => $line->part->unit->allowsDecimals(),
                // "4.2" for inputs, "4,2" for display.
                'qty' => rtrim(rtrim($line->qty, '0'), '.'),
                'qty_label' => Quantity::format($line->qtyHundredths()),
                'unit_price' => $line->unit_price,
                'unit_price_label' => Money::format($line->unit_price),
                'line_total_label' => Money::format($line->line_total),
            ])->all()),
            'estimated_total_label' => $this->when($detail, fn () => Money::format($this->estimatedTotal())),
        ];
    }
}
