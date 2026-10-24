<?php

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding or renaming an expense category, switching it off, or making it restricted.
 */
class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCategories', Expense::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'is_restricted' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
