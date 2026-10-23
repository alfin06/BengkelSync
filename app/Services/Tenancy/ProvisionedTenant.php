<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;

final readonly class ProvisionedTenant
{
    public function __construct(
        public Tenant $tenant,
        public Branch $branch,
        public User $owner,
    ) {}
}
