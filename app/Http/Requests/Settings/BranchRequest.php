<?php

namespace App\Http\Requests\Settings;

use App\Models\Branch;
use App\Rules\IndonesianPhone;
use App\Services\Settings\BranchService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Adding a branch (branch.create) or editing one the user works in (branch.update).
 */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->branch();

        return $branch === null
            ? $this->user()?->can('create', Branch::class) ?? false
            : $this->user()?->can('update', $branch) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Str::upper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Part of every document number; unique in the shop, deleted branches included.
            'code' => [
                'required',
                'regex:/^[A-Z0-9]{2,6}$/',
                Rule::unique('branches', 'code')
                    ->where('tenant_id', app(TenantContext::class)->requireTenant()->getKey())
                    ->ignore($this->branch()?->getKey()),
            ],
            'timezone' => ['required', Rule::in(BranchService::TIMEZONES)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20', new IndonesianPhone],
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9.\-\s]+$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('branches.code_format'),
            'code.unique' => __('branches.code_taken'),
        ];
    }

    /**
     * @return array{name: string, code: string, timezone: string, address: string|null, phone: string|null, npwp: string|null}
     */
    public function branchData(): array
    {
        /** @var array{name: string, code: string, timezone: string, address: string|null, phone: string|null, npwp: string|null} */
        return $this->safe()->only(['name', 'code', 'timezone', 'address', 'phone', 'npwp']) + ['address' => null, 'phone' => null, 'npwp' => null];
    }

    private function branch(): ?Branch
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch ? $branch : null;
    }
}
