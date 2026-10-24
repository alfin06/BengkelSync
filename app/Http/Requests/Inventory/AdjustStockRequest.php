<?php

namespace App\Http\Requests\Inventory;

use App\Models\Part;
use App\Rules\StockQuantity;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Stock opname: the quantity actually counted on the shelf, with a reason.
 */
class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjustStock', $this->part()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted' => ['required', new StockQuantity($this->part()->unit)],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function counted(): int
    {
        return Quantity::parse((string) $this->validated('counted'));
    }

    public function part(): Part
    {
        /** @var Part $part */
        $part = $this->route('part');

        return $part;
    }
}
