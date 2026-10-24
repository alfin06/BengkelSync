<?php

namespace App\Http\Requests\WorkOrders;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Rules\IndonesianPhone;
use App\Rules\IndonesianPlate;
use App\Support\BranchTime;
use App\Support\PlateNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Check-in on one screen: a known vehicle (vehicle_id), or a new vehicle for a known
 * customer (customer_id) or a new customer (customer.*).
 */
class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', WorkOrder::class)) {
            return false;
        }

        if ($this->input('mode') !== 'new') {
            return true;
        }

        return $user->can('create', Vehicle::class)
            && ($this->filled('customer_id') || $user->can('create', Customer::class));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['existing', 'new'])],
            'vehicle_id' => ['exclude_unless:mode,existing', 'required', 'integer'],

            'customer_id' => ['exclude_unless:mode,new', 'nullable', 'integer'],
            'customer.name' => ['exclude_unless:mode,new', 'exclude_with:customer_id', 'required', 'string', 'max:255'],
            'customer.phone' => ['exclude_unless:mode,new', 'exclude_with:customer_id', 'nullable', 'string', 'max:20', new IndonesianPhone],
            'vehicle.plate_number' => ['exclude_unless:mode,new', 'required', 'string', 'max:20', new IndonesianPlate],
            'vehicle.make' => ['exclude_unless:mode,new', 'required', 'string', 'max:50'],
            'vehicle.model' => ['exclude_unless:mode,new', 'required', 'string', 'max:80'],
            'vehicle.year' => ['exclude_unless:mode,new', 'nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'vehicle.color' => ['exclude_unless:mode,new', 'nullable', 'string', 'max:30'],

            'complaint' => ['required', 'string', 'max:2000'],
            'odometer_in' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'estimated_completion' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tasks' => ['array', 'max:30'],
            'tasks.*' => ['nullable', 'string', 'max:255'],
            'mechanic_ids' => ['array', 'max:10'],
            'mechanic_ids.*' => ['integer', 'distinct'],
            'draft' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('mode') === 'existing') {
                if (! $validator->errors()->has('vehicle_id') && $this->vehicle() === null) {
                    $validator->errors()->add('vehicle_id', __('work_orders.vehicle_not_found'));
                }

                return;
            }

            if ($this->filled('customer_id') && $this->customer() === null) {
                $validator->errors()->add('customer_id', __('work_orders.customer_not_found'));
            }

            if (! $validator->errors()->has('vehicle.plate_number')) {
                $plate = PlateNumber::normalize($this->string('vehicle.plate_number')->toString());

                // Also deleted vehicles: restoring one is done from the customer page.
                if (Vehicle::withTrashed()->where('plate_number', $plate)->exists()) {
                    $validator->errors()->add('vehicle.plate_number', __('work_orders.plate_exists'));
                }
            }
        }];
    }

    public function vehicle(): ?Vehicle
    {
        return Vehicle::query()->find($this->integer('vehicle_id'));
    }

    public function customer(): ?Customer
    {
        return $this->filled('customer_id') ? Customer::query()->find($this->integer('customer_id')) : null;
    }

    /**
     * @return array{complaint: string, odometer_in: int|null, estimated_completion: string|null, notes: string|null}
     */
    public function workOrderData(): array
    {
        return [
            'complaint' => $this->string('complaint')->toString(),
            'odometer_in' => $this->filled('odometer_in') ? $this->integer('odometer_in') : null,
            'estimated_completion' => BranchTime::fromInput($this->input('estimated_completion')),
            'notes' => $this->input('notes'),
        ];
    }

    /**
     * @return array<string, mixed> fields for CustomerService::create()
     */
    public function customerData(): array
    {
        return [
            'type' => 'individual',
            'name' => $this->string('customer.name')->toString(),
            'phone' => $this->input('customer.phone'),
            'whatsapp_same_as_phone' => true,
        ];
    }

    /**
     * @return array<string, mixed> fields for VehicleService::create()
     */
    public function vehicleData(): array
    {
        return [
            'plate_number' => $this->string('vehicle.plate_number')->toString(),
            'make' => $this->string('vehicle.make')->toString(),
            'model' => $this->string('vehicle.model')->toString(),
            'year' => $this->filled('vehicle.year') ? $this->integer('vehicle.year') : null,
            'color' => $this->input('vehicle.color'),
        ];
    }

    /**
     * @return list<string>
     */
    public function tasks(): array
    {
        return array_values(array_map('strval', array_filter((array) $this->input('tasks', []), fn ($task) => is_string($task) && trim($task) !== '')));
    }

    /**
     * Assigning mechanics needs work_order.assign; without it the choice is ignored.
     *
     * @return list<int>
     */
    public function mechanicIds(): array
    {
        if (! ($this->user()?->can(Permission::WorkOrderAssign->value) ?? false)) {
            return [];
        }

        return array_values(array_map('intval', (array) $this->input('mechanic_ids', [])));
    }
}
