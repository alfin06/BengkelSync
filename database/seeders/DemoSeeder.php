<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Branch;
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
    }
}
