<?php

namespace App\Http\Requests\Payments;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One checkout: one or more payment methods with their amounts. The server checks the
 * amounts against what is still owed and calculates every surcharge itself.
 */
class CheckoutRequest extends FormRequest
{
    /** @var Collection<int, PaymentMethod>|null */
    private ?Collection $methods = null;

    public function authorize(): bool
    {
        return $this->user()?->can('pay', $this->invoice()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->invoice()->issue_date->toDateString(), 'before_or_equal:'.app(TenantContext::class)->today()],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:paid_on'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:10'],
            'lines.*.payment_method_id' => ['required', 'integer'],
            'lines.*.amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'lines.*.cash_tendered' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'lines.*.reference' => ['nullable', 'string', 'max:80'],
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

            foreach ((array) $this->input('lines', []) as $index => $line) {
                $method = $this->methods()->get((int) ($line['payment_method_id'] ?? 0));

                if ($method === null || ! $method->is_active) {
                    $validator->errors()->add("lines.{$index}.payment_method_id", __('payments.method_unavailable'));
                } elseif ($method->needs_reference && trim((string) ($line['reference'] ?? '')) === '') {
                    $validator->errors()->add("lines.{$index}.reference", __('payments.reference_required', ['method' => $method->name]));
                }
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
     * @return list<array{method: PaymentMethod, amount: int, cash_tendered: int|null, reference: string|null}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $line) => [
            // Validated above: every method exists in this branch.
            'method' => $this->methods()->get((int) $line['payment_method_id']) ?? abort(422),
            'amount' => (int) $line['amount'],
            'cash_tendered' => isset($line['cash_tendered']) ? (int) $line['cash_tendered'] : null,
            'reference' => isset($line['reference']) ? (string) $line['reference'] : null,
        ], (array) $this->input('lines', [])));
    }

    /**
     * The branch's methods used on this checkout (branch-scoped), with their accounts.
     *
     * @return Collection<int, PaymentMethod>
     */
    private function methods(): Collection
    {
        return $this->methods ??= PaymentMethod::query()
            ->with('account')
            ->whereKey(array_map(fn ($line) => (int) (is_array($line) ? ($line['payment_method_id'] ?? 0) : 0), (array) $this->input('lines', [])))
            ->get()
            ->keyBy('id');
    }
}
