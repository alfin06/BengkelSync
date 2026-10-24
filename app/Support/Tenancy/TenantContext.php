<?php

namespace App\Support\Tenancy;

use App\Models\Branch;
use App\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * Holds the tenant and active branch for the current request, job or command.
 *
 * It is set by the InitializeTenancy middleware from the logged-in user (never
 * from request input), or explicitly with run() in console code and tests.
 * Registered as a scoped singleton, so it never leaks between requests.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?Branch $branch = null;

    public function __construct(private readonly PermissionRegistrar $permissions) {}

    public function set(Tenant $tenant, ?Branch $branch = null): void
    {
        if ($branch !== null && $branch->tenant_id !== $tenant->getKey()) {
            throw TenancyViolation::branchOutsideTenant();
        }

        $this->tenant = $tenant;
        $this->branch = $branch;
        $this->permissions->setPermissionsTeamId($tenant->getKey());
    }

    public function setBranch(?Branch $branch): void
    {
        $this->set($this->requireTenant(), $branch);
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->branch = null;
        $this->permissions->setPermissionsTeamId(null);
    }

    /**
     * Run a callback inside a tenant (and optional branch), then restore the previous context.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, ?Branch $branch, callable $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousBranch = $this->branch;

        $this->set($tenant, $branch);

        try {
            return $callback();
        } finally {
            if ($previousTenant === null) {
                $this->clear();
            } else {
                $this->set($previousTenant, $previousBranch);
            }
        }
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function requireTenant(): Tenant
    {
        return $this->tenant ?? throw TenancyViolation::missingTenant();
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function branchId(): ?int
    {
        return $this->branch?->getKey();
    }

    public function requireBranch(): Branch
    {
        return $this->branch ?? throw TenancyViolation::missingBranch();
    }

    /**
     * Today's date (Y-m-d) in the active branch's timezone (rule 11): 06:00 in Jakarta is
     * still "today" there, although it is yesterday in UTC.
     */
    public function today(): string
    {
        return now($this->branch->timezone ?? 'Asia/Jakarta')->toDateString();
    }
}
