<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangeVehicleOwnerRequest extends FormRequest
{
    private ?Customer $newOwner = null;

    public function authorize(): bool
    {
        return $this->user()?->can('changeOwner', $this->route('vehicle')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The new owner must be a customer of this tenant; others are not found because
     * Customer is tenant-scoped.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('customer_id') && $this->newOwner() === null) {
                $validator->errors()->add('customer_id', __('validation.exists', ['attribute' => __('vehicles.new_owner')]));
            }
        }];
    }

    public function newOwner(): ?Customer
    {
        return $this->newOwner ??= Customer::query()->find($this->integer('customer_id'));
    }
}
