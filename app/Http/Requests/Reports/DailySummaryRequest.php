<?php

namespace App\Http\Requests\Reports;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The day of the "Rekap harian" (report.view); today in the branch's time zone by default.
 */
class DailySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ReportView->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.app(TenantContext::class)->today()],
        ];
    }

    public function day(): string
    {
        return $this->filled('date') ? $this->string('date')->toString() : app(TenantContext::class)->today();
    }
}
