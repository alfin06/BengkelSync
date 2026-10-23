<?php

namespace App\Services\Tenancy;

final readonly class TenantProvisioningData
{
    public function __construct(
        public string $shopName,
        public string $branchName,
        public string $branchCode,
        public string $ownerName,
        public string $ownerEmail,
        public string $ownerPassword,
        public string $timezone = 'Asia/Jakarta',
        public string $locale = 'id',
    ) {}
}
