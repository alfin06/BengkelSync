<?php

namespace App\Services\Tenancy;

use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\Cash\CashSetupService;
use App\Services\Expenses\ExpenseCategoryService;
use App\Services\Permissions\PermissionSynchronizer;
use App\Services\Permissions\RoleProvisioner;
use App\Services\Users\UserService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a ready-to-use tenant: tenant, first branch (with its default cash accounts and
 * payment method), default roles and the owner (SPEC section 3), in one transaction.
 *
 * Phase 1 modules add their own defaults here (expense categories, ...).
 */
class TenantProvisioner
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionSynchronizer $permissions,
        private readonly RoleProvisioner $roles,
        private readonly UserService $users,
        private readonly CashSetupService $cash,
        private readonly ExpenseCategoryService $expenseCategories,
    ) {}

    public function provision(TenantProvisioningData $data): ProvisionedTenant
    {
        $this->permissions->sync();

        return DB::transaction(function () use ($data): ProvisionedTenant {
            $tenant = Tenant::query()->create([
                'name' => $data->shopName,
                'slug' => $this->uniqueSlug($data->shopName),
                'status' => TenantStatus::Active,
                'default_locale' => $data->locale,
            ]);

            return $this->context->run($tenant, null, function () use ($tenant, $data): ProvisionedTenant {
                $branch = Branch::query()->create([
                    'name' => $data->branchName,
                    'code' => strtoupper($data->branchCode),
                    'timezone' => $data->timezone,
                ]);

                $this->roles->provision($tenant);
                $this->cash->provisionDefaults($branch);
                $this->expenseCategories->provisionDefaults($tenant);

                $owner = $this->users->create(
                    attributes: [
                        'name' => $data->ownerName,
                        'email' => $data->ownerEmail,
                        'password' => $data->ownerPassword,
                        'locale' => $data->locale,
                        'all_branches' => true,
                    ],
                    role: RoleName::Owner,
                    branchIds: [$branch->id],
                );

                return new ProvisionedTenant($tenant, $branch, $owner);
            });
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'bengkel';
        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
