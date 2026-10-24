<?php

namespace App\Http\Requests\Inventory;

use App\Enums\PartUnit;
use App\Models\Part;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for adding and editing a catalog part.
 */
abstract class PartRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', Rule::enum(PartUnit::class)],
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'sell_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * SKUs are unique within the tenant, including deleted parts (database unique key).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $sku = strtoupper(trim($this->string('sku')->toString()));

            if ($sku === '' || $validator->errors()->has('sku')) {
                return;
            }

            $taken = Part::withTrashed()
                ->where('sku', $sku)
                ->when($this->ignoredId(), fn ($query, int $id) => $query->whereKeyNot($id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('sku', __('validation.unique', ['attribute' => 'SKU']));
            }
        }];
    }

    public function unit(): ?PartUnit
    {
        return PartUnit::tryFrom($this->string('unit')->toString());
    }

    /**
     * @return array<string, mixed>
     */
    public function partData(): array
    {
        $data = collect($this->validated())
            ->only(['sku', 'name', 'brand', 'category', 'unit', 'cost_price', 'sell_price', 'is_active', 'notes'])
            ->all();

        // Users who may not see purchase costs cannot set them either.
        if (! ($this->user()?->can('viewCost', Part::class) ?? false)) {
            unset($data['cost_price']);
        }

        return $data;
    }

    protected function ignoredId(): ?int
    {
        return null;
    }
}
