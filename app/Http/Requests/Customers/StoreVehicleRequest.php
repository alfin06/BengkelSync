<?php

namespace App\Http\Requests\Customers;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends VehicleRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'restore_deleted' => ['boolean'],
        ];
    }

    /**
     * A deleted vehicle with this plate exists: ask the user to confirm restoring it.
     * The `restorable` error tells the form to show the confirmation.
     */
    protected function handleDeletedPlate(Validator $validator): void
    {
        if ($this->boolean('restore_deleted')) {
            return;
        }

        $validator->errors()->add('plate_number', __('vehicles.plate_deleted'));
        $validator->errors()->add('restorable', '1');
    }
}
