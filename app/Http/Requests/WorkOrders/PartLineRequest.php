<?php

namespace App\Http\Requests\WorkOrders;

use App\Models\Part;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Rules\StockQuantity;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A part on a work order: quantity (decimals only for liter/meter/kg) and the selling
 * price per unit, prefilled with the part's price and changeable by staff.
 */
class PartLineRequest extends FormRequest
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
            'part_id' => [$this->line() === null ? 'required' : 'prohibited', 'integer'],
            'qty' => ['required', new StockQuantity],
            'unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $part = $this->part();
            if ($part === null) {
                $validator->errors()->add('part_id', __('purchases.unknown_part'));

                return;
            }

            $qty = Quantity::parse($this->string('qty')->toString());
            if ($qty <= 0) {
                $validator->errors()->add('qty', __('stock.zero_change'));
            } elseif (! $part->unit->allowsDecimals() && ! Quantity::isWhole($qty)) {
                $validator->errors()->add('qty', __('stock.whole_units_only', ['unit' => $part->unit->label()]));
            }
        }];
    }

    public function line(): ?WorkOrderPart
    {
        $line = $this->route('part');

        return $line instanceof WorkOrderPart ? $line : null;
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

    public function part(): ?Part
    {
        $line = $this->line();

        return $line !== null ? $line->part : Part::query()->find($this->integer('part_id'));
    }

    public function qtyHundredths(): int
    {
        return Quantity::parse($this->string('qty')->toString());
    }
}
