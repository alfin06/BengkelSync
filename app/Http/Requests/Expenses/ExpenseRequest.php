<?php

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recording (no route expense) or editing an expense. Amount and account are only set
 * when recording; a wrong one is corrected by voiding and recording again.
 */
class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? ($this->user()?->can('update', $expense) ?? false)
            : ($this->user()?->can('create', Expense::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = ! $this->route('expense') instanceof Expense;

        return [
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.app(TenantContext::class)->today()],
            'category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => [$creating ? 'required' : 'prohibited', 'integer', 'min:1', 'max:999999999999'],
            'cash_account_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            // A photo of the receipt from the phone camera, or a PDF.
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'remove_receipt' => ['boolean'],
        ];
    }

    /**
     * @return array{expense_date: string, category_id: int, description: string, amount: int, cash_account_id: int}
     */
    public function expenseData(): array
    {
        return [
            'expense_date' => $this->string('expense_date')->toString(),
            'category_id' => $this->integer('category_id'),
            'description' => $this->string('description')->toString(),
            'amount' => $this->integer('amount'),
            'cash_account_id' => $this->integer('cash_account_id'),
        ];
    }
}
