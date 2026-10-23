<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ServiceItem;
use App\Models\Vehicle;
use App\Services\Customers\VehicleService;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\TenantProvisioningData;
use App\Services\Users\UserService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo data for local development only. All accounts use the password in PASSWORD
 * and fictional `.test` addresses (see README). Never runs in production.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password1';

    public function run(TenantProvisioner $provisioner, UserService $users, TenantContext $context): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must never run in production.');
        }

        $demo = $provisioner->provision(new TenantProvisioningData(
            shopName: 'Bengkel Demo',
            branchName: 'Pusat',
            branchCode: 'DMO',
            ownerName: 'Pemilik Demo',
            ownerEmail: 'owner@demo.test',
            ownerPassword: self::PASSWORD,
        ));

        $context->run($demo->tenant, null, function () use ($demo, $users): void {
            $second = Branch::query()->create(['name' => 'Cabang Timur', 'code' => 'DMT']);
            $main = $demo->branch->id;

            $accounts = [
                ['Kepala Cabang Demo', 'manager@demo.test', RoleName::BranchManager, [$main, $second->id]],
                ['Admin Depan Demo', 'frontdesk@demo.test', RoleName::FrontDesk, [$main]],
                ['Keuangan Demo', 'finance@demo.test', RoleName::Finance, [$main]],
                ['Gudang Demo', 'warehouse@demo.test', RoleName::Warehouse, [$main, $second->id]],
            ];

            foreach ($accounts as [$name, $email, $role, $branchIds]) {
                $users->create(
                    attributes: ['name' => $name, 'email' => $email, 'password' => self::PASSWORD],
                    role: $role,
                    branchIds: $branchIds,
                );
            }
        });

        $context->run($demo->tenant, $demo->branch, function (): void {
            $this->customers();
            $this->serviceCatalog();
        });
    }

    /**
     * A typical small-shop price list (example prices).
     */
    private function serviceCatalog(): void
    {
        $services = [
            ['GO-01', 'Ganti oli mesin', 'Perawatan', 50_000, 30],
            ['GO-02', 'Ganti oli transmisi', 'Perawatan', 75_000, 45],
            ['TU-01', 'Tune up mesin bensin', 'Perawatan', 250_000, 90],
            ['FU-01', 'Ganti filter udara', 'Perawatan', 25_000, 15],
            ['SP-01', 'Spooring', 'Kaki-kaki', 150_000, 45],
            ['BL-01', 'Balancing (4 roda)', 'Kaki-kaki', 100_000, 30],
            ['RM-01', 'Servis rem depan', 'Rem', 120_000, 60],
            ['RM-02', 'Ganti kampas rem', 'Rem', 80_000, 45],
            ['AC-01', 'Isi freon AC', 'AC', 200_000, 45],
            ['AC-02', 'Servis AC lengkap', 'AC', 450_000, 120],
            ['KL-01', 'Cek kelistrikan', 'Kelistrikan', 75_000, 30],
            [null, 'Cuci mesin', null, 60_000, 30],
        ];

        foreach ($services as [$code, $name, $category, $price, $minutes]) {
            ServiceItem::query()->create([
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'default_price' => $price,
                'estimated_minutes' => $minutes,
            ]);
        }
    }

    /**
     * Fictional customers with vehicles (Faker data, never real people).
     */
    private function customers(): void
    {
        $vehicles = app(VehicleService::class);

        Customer::factory()->count(18)->create()->each(function (Customer $customer, int $index) use ($vehicles): void {
            foreach (range(1, $index % 3 === 0 ? 2 : 1) as $ignored) {
                $vehicles->create($customer, Vehicle::factory()->raw());
            }
        });

        $company = Customer::factory()->company()->create(['npwp' => '01.234.567.8-901.000']);
        foreach (range(1, 3) as $ignored) {
            $vehicles->create($company, Vehicle::factory()->raw());
        }
    }
}
