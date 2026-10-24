<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceLineType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\WorkOrder;
use App\Rules\StockQuantity;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A new invoice: from a finished work order (prices and discounts per line), or a
 * walk-in sale (any lines, optional customer = "Pelanggan umum"). The server
 * recalculates every total; nothing computed by the browser is trusted.
 */
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = app(TenantContext::class)->today();

        return [
            'mode' => ['required', Rule::in(['work_order', 'walk_in'])],
            'work_order_id' => ['exclude_unless:mode,work_order', 'required', 'integer'],
            'customer_id' => ['exclude_unless:mode,walk_in', 'nullable', 'integer'],
            'issue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'tax_enabled' => ['nullable', 'boolean'],

            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'lines.*.discount_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            // From a work order: which line, quantities stay as on the work order.
            'lines.*.source' => ['exclude_unless:mode,work_order', 'required', Rule::in(['service', 'part'])],
            'lines.*.id' => ['exclude_unless:mode,work_order', 'required', 'integer'],
            // Walk-in sale.
            'lines.*.type' => ['exclude_unless:mode,walk_in', 'required', Rule::enum(InvoiceLineType::class)],
            'lines.*.part_id' => ['exclude_unless:mode,walk_in', 'nullable', 'required_if:lines.*.type,part', 'integer'],
            'lines.*.service_item_id' => ['exclude_unless:mode,walk_in', 'nullable', 'integer'],
            'lines.*.description' => ['exclude_unless:mode,walk_in', 'nullable', 'required_unless:lines.*.type,part', 'string', 'max:255'],
            'lines.*.qty' => ['exclude_unless:mode,walk_in', 'required', new StockQuantity],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();
            $discounts = (int) $this->input('discount_amount', 0) + array_sum(array_map(
                fn ($line) => is_array($line) ? (int) ($line['discount_amount'] ?? 0) : 0,
                (array) $this->input('lines', []),
            ));

            if ($discounts > 0 && ! ($user?->can('discount', Invoice::class) ?? false)) {
                $validator->errors()->add('discount_amount', __('invoices.discount_not_allowed'));
            }

            if ($this->input('mode') === 'work_order' && ! $validator->errors()->has('work_order_id') && $this->workOrder() === null) {
                $validator->errors()->add('work_order_id', __('invoices.work_order_not_found'));
            }

            if ($this->input('mode') === 'walk_in' && $this->filled('customer_id') && $this->customer() === null) {
                $validator->errors()->add('customer_id', __('work_orders.customer_not_found'));
            }
        }];
    }

    public function workOrder(): ?WorkOrder
    {
        return WorkOrder::query()->find($this->integer('work_order_id'));
    }

    public function customer(): ?Customer
    {
        return $this->filled('customer_id') ? Customer::query()->find($this->integer('customer_id')) : null;
    }

    /**
     * @return array{issue_date: string, due_date: string|null, notes: string|null, discount_amount: int, tax_enabled: bool|null}
     */
    public function invoiceHeader(): array
    {
        $canToggleTax = $this->user()?->can('toggleTax', Invoice::class) ?? false;

        return [
            'issue_date' => $this->string('issue_date')->toString(),
            'due_date' => $this->filled('due_date') ? $this->string('due_date')->toString() : null,
            'notes' => $this->input('notes'),
            'discount_amount' => $this->integer('discount_amount'),
            // Without invoice.toggle_tax the branch setting applies.
            'tax_enabled' => $canToggleTax && $this->has('tax_enabled') && $this->input('tax_enabled') !== null ? $this->boolean('tax_enabled') : null,
        ];
    }

    /**
     * @return list<array{source: string, id: int, unit_price: int, discount_amount: int}>
     */
    public function workOrderPrices(): array
    {
        return array_values(array_map(fn (array $line) => [
            'source' => (string) $line['source'],
            'id' => (int) $line['id'],
            'unit_price' => (int) $line['unit_price'],
            'discount_amount' => (int) ($line['discount_amount'] ?? 0),
        ], (array) $this->input('lines', [])));
    }

    /**
     * @return list<array{type: string, part_id: int|null, service_item_id: int|null, description: string|null, qty: int, unit_price: int, discount_amount: int}>
     */
    public function walkInLines(): array
    {
        return array_values(array_map(fn (array $line) => [
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
