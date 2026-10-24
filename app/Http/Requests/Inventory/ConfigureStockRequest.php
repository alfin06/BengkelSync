<?php

namespace App\Http\Requests\Inventory;

use App\Models\Part;
use App\Rules\StockQuantity;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Low-stock threshold and shelf location of a part in the active branch.
 */
class ConfigureStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configureStock', $this->part()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min_stock' => ['required', new StockQuantity($this->part()->unit)],
            'location' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function minStock(): int
    {
        return Quantity::parse((string) $this->validated('min_stock'));
    }

    public function part(): Part
    {
        /** @var Part $part */
        $part = $this->route('part');

        return $part;
    }
}
