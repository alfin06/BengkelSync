<?php

namespace App\Http\Requests\Customers;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Vehicle;
use App\Rules\IndonesianPlate;
use App\Support\PlateNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating and editing a vehicle. Plate numbers are unique per tenant,
 * including deleted vehicles (decision 3 of the Phase 1 plan).
 */
abstract class VehicleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:20', new IndonesianPlate],
            'make' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:30'],
            'vin' => ['nullable', 'string', 'max:30', 'alpha_num:ascii'],
            'engine_number' => ['nullable', 'string', 'max:30', 'alpha_num:ascii'],
            'transmission' => ['nullable', Rule::enum(Transmission::class)],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'last_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('plate_number')) {
                return;
            }

            $existing = Vehicle::withTrashed()
                ->where('plate_number', PlateNumber::normalize($this->string('plate_number')->toString()))
                ->when($this->ignoredVehicleId(), fn ($query, int $id) => $query->whereKeyNot($id))
                ->first();

            if ($existing === null) {
                return;
            }

            if (! $existing->trashed()) {
                $validator->errors()->add('plate_number', __('vehicles.plate_taken'));

                return;
            }

            $this->handleDeletedPlate($validator);
        }];
    }

    /**
     * The vehicle being edited, which may keep its own plate.
     */
    protected function ignoredVehicleId(): ?int
    {
        return null;
    }

    protected function handleDeletedPlate(Validator $validator): void
    {
        $validator->errors()->add('plate_number', __('vehicles.plate_deleted'));
    }
}
