<?php

namespace App\Http\Requests\Purchasing;

use App\Models\Part;
use App\Rules\StockQuantity;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Shared rules for the lines of a purchase: parts of this tenant, quantities their unit
 * allows (decimals only for liter/meter/kg), whole-Rupiah unit costs.
 */
abstract class PurchaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_ref' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.part_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', new StockQuantity],
            'lines.*.unit_cost' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $lines = $this->input('lines');
            if (! is_array($lines)) {
                return;
            }

            $parts = Part::query()->whereKey(array_column($lines, 'part_id'))->get()->keyBy('id');

            foreach ($lines as $index => $line) {
                $part = $parts->get((int) ($line['part_id'] ?? 0));

                if ($part === null) {
                    $validator->errors()->add("lines.{$index}.part_id", __('purchases.unknown_part'));

                    continue;
                }

                $qty = $line['qty'] ?? null;
                if (Quantity::isValid($qty) && ! $part->unit->allowsDecimals() && ! Quantity::isWhole(Quantity::parse($qty))) {
                    $validator->errors()->add("lines.{$index}.qty", __('stock.whole_units_only', ['unit' => $part->unit->label()]));
                }

                if (Quantity::isValid($qty) && Quantity::parse($qty) <= 0) {
                    $validator->errors()->add("lines.{$index}.qty", __('stock.zero_change'));
                }
            }
        }];
    }

    /**
     * The lines with quantities in hundredths. Reads the input directly (not validated()),
     * so it is also safe inside after-validation hooks; only call it once the line rules
     * passed.
     *
     * @return list<array{part_id: int, qty: int, unit_cost: int}>
     */
    public function lines(): array
    {
        /** @var list<array{part_id: int|string, qty: string|int, unit_cost: int|string}> $lines */
        $lines = array_values((array) $this->input('lines', []));

        return array_map(fn (array $line) => [
            'part_id' => (int) $line['part_id'],
            'qty' => Quantity::parse((string) $line['qty']),
            'unit_cost' => (int) $line['unit_cost'],
        ], $lines);
    }
}
