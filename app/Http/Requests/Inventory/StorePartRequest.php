<?php

namespace App\Http\Requests\Inventory;

use App\Models\Part;
use App\Rules\StockQuantity;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\ValidationRule;

class StorePartRequest extends PartRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Part::class) ?? false;
    }

    /**
     * Opening stock is a stock correction, so it needs stock.adjust.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $canAdjust = $this->user()?->can('adjustStock', Part::class) ?? false;

        return [
            ...parent::rules(),
            'opening_stock' => $canAdjust ? ['nullable', new StockQuantity($this->unit())] : ['prohibited'],
            'min_stock' => ['nullable', new StockQuantity($this->unit())],
            'location' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function openingStock(): ?int
    {
        $value = $this->validated('opening_stock');

        return $value === null || $value === '' ? null : Quantity::parse((string) $value);
    }

    public function minStock(): int
    {
        $value = $this->validated('min_stock');

        return $value === null || $value === '' ? 0 : Quantity::parse((string) $value);
    }

    public function location(): ?string
    {
        $value = $this->validated('location');

        return is_string($value) ? $value : null;
    }
}
