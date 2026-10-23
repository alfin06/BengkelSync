<?php

use App\Enums\DocumentType;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\ProvisionedTenant;
use App\Support\DocumentNumber;
use Illuminate\Support\Str;

// CLAUDE.md rule 1: every tenant-owned model proves tenant A cannot read or write tenant B's data.

it('isolates branches', function () {
    assertTenantIsolated(Branch::class, fn (ProvisionedTenant $shop) => Branch::query()->create([
        'name' => 'Cabang '.Str::random(4),
        'code' => Str::upper(Str::random(4)),
    ]));
});

it('isolates users', function () {
    assertTenantIsolated(User::class, fn (ProvisionedTenant $shop) => userWithRole($shop, RoleName::FrontDesk));
});

it('isolates audit logs', function () {
    assertTenantIsolated(AuditLog::class, fn (ProvisionedTenant $shop) => app(AuditLogger::class)->record('test', $shop->branch));
});

it('isolates document sequences', function () {
    assertTenantIsolated(DocumentSequence::class, function (ProvisionedTenant $shop) {
        DocumentNumber::next($shop->branch, DocumentType::Invoice);

        return DocumentSequence::query()->latest('id')->firstOrFail();
    });
});
