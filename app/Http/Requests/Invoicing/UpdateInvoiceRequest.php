<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceLineType;
use App\Models\Invoice;
use App\Rules\StockQuantity;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Editing an issued invoice: its lines, discount, tax (with invoice.toggle_tax), due
 * date and notes, always with a reason. The server recalculates every total.
 */
class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->invoice()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$this->invoice()->issue_date->toDateString()],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'tax_enabled' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.type' => ['required', Rule::enum(InvoiceLineType::class)],
            'lines.*.part_id' => ['nullable', 'required_if:lines.*.type,part', 'integer'],
            'lines.*.service_item_id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'required_unless:lines.*.type,part', 'string', 'max:255'],
            'lines.*.qty' => ['required', new StockQuantity],
            'lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'lines.*.discount_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $discounts = (int) $this->input('discount_amount', 0) + array_sum(array_map(
                fn ($line) => is_array($line) ? (int) ($line['discount_amount'] ?? 0) : 0,
                (array) $this->input('lines', []),
            ));

            // Keeping a discount that is already there is fine; adding or raising one needs invoice.discount.
            $current = $this->invoice()->discount_amount + (int) $this->invoice()->lines()->sum('discount_amount');
            if ($discounts > $current && ! ($this->user()?->can('discount', Invoice::class) ?? false)) {
                $validator->errors()->add('discount_amount', __('invoices.discount_not_allowed'));
            }
        }];
    }

    public function invoice(): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');

        return $invoice;
    }

    /**
     * @return array{due_date: string|null, notes: string|null, discount_amount: int, tax_enabled: bool|null}
     */
    public function invoiceHeader(): array
    {
        $canToggleTax = $this->user()?->can('toggleTax', Invoice::class) ?? false;

        return [
            'due_date' => $this->filled('due_date') ? $this->string('due_date')->toString() : null,
            'notes' => $this->input('notes'),
            'discount_amount' => $this->integer('discount_amount'),
            // Without invoice.toggle_tax the invoice keeps its tax setting.
            'tax_enabled' => $canToggleTax && $this->input('tax_enabled') !== null ? $this->boolean('tax_enabled') : null,
        ];
    }

    /**
     * @return list<array{id: int|null, type: string, part_id: int|null, service_item_id: int|null, description: string|null, qty: int, unit_price: int, discount_amount: int}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $line) => [
            'id' => isset($line['id']) ? (int) $line['id'] : null,
            'type' => (string) $line['type'],
            'part_id' => isset($line['part_id']) ? (int) $line['part_id'] : null,
            'service_item_id' => isset($line['service_item_id']) ? (int) $line['service_item_id'] : null,
            'description' => isset($line['description']) ? (string) $line['description'] : null,
            'qty' => Quantity::parse((string) $line['qty']),
            'unit_price' => (int) $line['unit_price'],
            'discount_amount' => (int) ($line['discount_amount'] ?? 0),
        ], (array) $this->input('lines', [])));
    }
}
