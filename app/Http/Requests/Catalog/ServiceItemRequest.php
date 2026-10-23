<?php

namespace App\Http\Requests\Catalog;

use App\Models\ServiceItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Shared rules for adding and editing a catalog job.
 */
abstract class ServiceItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'default_price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Codes are unique within the tenant (the query is tenant-scoped).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $code = strtoupper(trim($this->string('code')->toString()));

            if ($code === '' || $validator->errors()->has('code')) {
                return;
            }

            $taken = ServiceItem::query()
                ->where('code', $code)
                ->when($this->ignoredId(), fn ($query, int $id) => $query->whereKeyNot($id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('code', __('validation.unique', ['attribute' => __('service_items.fields.code')]));
            }
        }];
    }

    protected function ignoredId(): ?int
    {
        return null;
    }
}
