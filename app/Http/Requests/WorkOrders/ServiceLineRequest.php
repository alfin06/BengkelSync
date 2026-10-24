<?php

namespace App\Http\Requests\WorkOrders;

use App\Models\WorkOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A job on a work order: description and price typed by staff (a catalog job only
 * prefills them).
 */
class ServiceLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('editLines', $this->workOrder()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_item_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'mechanic_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Lines are bound through their work order (scoped bindings), which is branch-scoped.
     */
    public function workOrder(): WorkOrder
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('work_order');

        return $workOrder;
    }

    /**
     * @return array{service_item_id: int|null, description: string, qty: int, unit_price: int, mechanic_id: int|null}
     */
    public function lineData(): array
    {
        return [
            'service_item_id' => $this->filled('service_item_id') ? $this->integer('service_item_id') : null,
            'description' => $this->string('description')->toString(),
            'qty' => $this->integer('qty'),
            'unit_price' => $this->integer('unit_price'),
            'mechanic_id' => $this->filled('mechanic_id') ? $this->integer('mechanic_id') : null,
        ];
    }
}
