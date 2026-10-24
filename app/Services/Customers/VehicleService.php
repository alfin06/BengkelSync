<?php

namespace App\Services\Customers;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Support\PlateNumber;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * All vehicle writes, including owner changes (SPEC section 4, vehicle_ownerships).
 * The vehicle's customer_id and the open ownership row always change together.
 */
class VehicleService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     * @param  bool  $restoreDeleted  the user confirmed restoring a deleted vehicle with the same plate
     */
    public function create(Customer $owner, array $data, bool $restoreDeleted = false): Vehicle
    {
        $data = $this->prepare($data);

        return DB::transaction(function () use ($owner, $data, $restoreDeleted): Vehicle {
            $deleted = Vehicle::onlyTrashed()->where('plate_number', $data['plate_number'])->lockForUpdate()->first();

            if ($deleted !== null) {
                if (! $restoreDeleted) {
                    throw new BusinessRuleViolation(__('vehicles.plate_deleted'));
                }

                $deleted->restore();
                $deleted->fill($data)->save();

                if ($deleted->customer_id !== $owner->id) {
                    $this->changeOwner($deleted, $owner, __('vehicles.restored_reason'));
                }

                return $deleted;
            }

            $vehicle = new Vehicle($data);
            $vehicle->forceFill(['customer_id' => $owner->id])->save();

            VehicleOwnership::query()->create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $owner->id,
                'started_on' => $this->today(),
            ]);

            return $vehicle;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        DB::transaction(fn () => $vehicle->fill($this->prepare($data))->save());

        return $vehicle;
    }

    public function changeOwner(Vehicle $vehicle, Customer $newOwner, ?string $reason = null): void
    {
        if ($vehicle->customer_id === $newOwner->id) {
            throw new BusinessRuleViolation(__('vehicles.same_owner'));
        }

        DB::transaction(function () use ($vehicle, $newOwner, $reason): void {
            $today = $this->today();

            VehicleOwnership::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('ended_on')
                ->update(['ended_on' => $today]);

            VehicleOwnership::query()->create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $newOwner->id,
                'started_on' => $today,
                'reason' => $reason,
            ]);

            // Audited by the Auditable trait (old and new customer_id).
            $vehicle->forceFill(['customer_id' => $newOwner->id])->save();
        });
    }

    public function delete(Vehicle $vehicle): void
    {
        DB::transaction(fn () => $vehicle->delete());
    }

    /**
     * Makes and models the shop already entered, for the form's suggestion lists.
     *
     * @return array{makes: list<string>, models: array<string, list<string>>}
     */
    public function suggestions(): array
    {
        $pairs = Vehicle::query()
            ->select(['make', 'model'])
            ->distinct()
            ->orderBy('make')
            ->orderBy('model')
            ->limit(500)
            ->get();

        $models = [];
        foreach ($pairs as $pair) {
            $models[$pair->make][] = $pair->model;
        }

        return ['makes' => array_keys($models), 'models' => $models];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $data['plate_number'] = PlateNumber::normalize((string) $data['plate_number']);
        $data['make'] = trim((string) $data['make']);
        $data['model'] = trim((string) $data['model']);

        foreach (['vin', 'engine_number'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtoupper(trim((string) $data[$field]));
            }
        }

        unset($data['restore_deleted']);

        return $data;
    }

    private function today(): string
    {
        return $this->context->today();
    }
}
