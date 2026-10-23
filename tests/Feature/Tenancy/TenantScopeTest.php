<?php

use App\Models\Branch;
use App\Models\User;
use App\Support\Tenancy\TenancyViolation;
use App\Support\Tenancy\TenantContext;

it('only returns rows of the current tenant', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    $names = inTenant($a->tenant, null, fn () => Branch::query()->pluck('name')->all());

    expect($names)->toBe([$a->branch->name]);
    expect(inTenant($a->tenant, null, fn () => Branch::query()->find($b->branch->id)))->toBeNull();
});

it('throws when tenant data is queried without a tenant', function () {
    provisionTenant();

    Branch::query()->get();
})->throws(TenancyViolation::class);

it('allows looking up users without a tenant, for login and password reset', function () {
    $shop = provisionTenant();

    expect(User::query()->where('email', $shop->owner->email)->exists())->toBeTrue();
});

it('scopes users to the current tenant inside a tenant', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    $emails = inTenant($a->tenant, null, fn () => User::query()->pluck('email')->all());

    expect($emails)->toContain($a->owner->email)->not->toContain($b->owner->email);
});

it('fills tenant_id from the context on create', function () {
    $shop = provisionTenant();

    $branch = inTenant($shop->tenant, null, fn () => Branch::query()->create(['name' => 'Cabang Baru', 'code' => 'NEW']));

    expect($branch->tenant_id)->toBe($shop->tenant->id);
});

it('refuses to create a row without a tenant', function () {
    Branch::query()->create(['name' => 'Tanpa Tenant', 'code' => 'XXX']);
})->throws(TenancyViolation::class);

it('refuses to move a row to another tenant', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    inTenant($a->tenant, null, function () use ($a, $b) {
        $a->branch->tenant_id = $b->tenant->id;
        $a->branch->save();
    });
})->throws(TenancyViolation::class);

it('refuses a branch of another tenant as the active branch', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    app(TenantContext::class)->set($a->tenant, $b->branch);
})->throws(TenancyViolation::class);

it('restores the previous context after run()', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');
    $context = app(TenantContext::class);

    $context->set($a->tenant, $a->branch);
    $inner = $context->run($b->tenant, $b->branch, fn () => $context->tenantId());

    expect($inner)->toBe($b->tenant->id)
        ->and($context->tenantId())->toBe($a->tenant->id)
        ->and($context->branchId())->toBe($a->branch->id);

    $context->clear();
});
