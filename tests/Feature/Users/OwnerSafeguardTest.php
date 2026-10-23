<?php

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use App\Services\Users\UserService;
use Illuminate\Support\Facades\DB;

// SPEC section 2: at least one active owner must exist.

it('does not deactivate, delete or demote the last active owner', function (string $action) {
    $shop = provisionTenant();
    $users = app(UserService::class);

    inTenant($shop->tenant, null, fn () => match ($action) {
        'deactivate' => $users->deactivate($shop->owner),
        'delete' => $users->delete($shop->owner),
        'demote' => $users->changeRole($shop->owner, RoleName::Finance),
    });
})->with(['deactivate', 'delete', 'demote'])->throws(BusinessRuleViolation::class);

it('allows it when another active owner exists', function () {
    $shop = provisionTenant();
    userWithRole($shop, RoleName::Owner);

    inTenant($shop->tenant, null, fn () => app(UserService::class)->deactivate($shop->owner));

    expect($shop->owner->fresh()?->is_active)->toBeFalse();
});

it('does not count an inactive second owner', function () {
    $shop = provisionTenant();
    userWithRole($shop, RoleName::Owner, attributes: ['is_active' => false]);

    inTenant($shop->tenant, null, fn () => app(UserService::class)->deactivate($shop->owner));
})->throws(BusinessRuleViolation::class);

it('ends the sessions of a deactivated user', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    inTenant($shop->tenant, null, fn () => app(UserService::class)->deactivate($user));

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('records role changes in the audit log', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);

    inTenant($shop->tenant, null, fn () => app(UserService::class)->changeRole($user, RoleName::Finance));

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $shop->tenant->id,
        'action' => 'role_changed',
        'auditable_id' => $user->id,
    ]);
});

it('refuses to assign branches of another tenant', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    userWithRole($a, RoleName::FrontDesk, [$b->branch]);
})->throws(BusinessRuleViolation::class);
