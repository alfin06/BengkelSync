<?php

use App\Enums\Permission as PermissionName;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Role;
use App\Services\Permissions\PermissionSynchronizer;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

it('syncs the permissions table with the enum and removes obsolete ones', function () {
    Permission::query()->create(['name' => 'obsolete.thing', 'guard_name' => 'web']);

    app(PermissionSynchronizer::class)->sync();

    expect(Permission::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionName::values())->sort()->values()->all());
});

it('gives every new tenant its own copy of the default roles', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    foreach ([$a, $b] as $shop) {
        expect(Role::query()->forTenant($shop->tenant)->pluck('name')->sort()->values()->all())
            ->toBe(collect(RoleName::cases())->map->value->sort()->values()->all());
    }
});

it('grants the default permissions per role', function (RoleName $role, array $allowed, array $denied) {
    $shop = provisionTenant();
    $user = userWithRole($shop, $role);

    inTenant($shop->tenant, $shop->branch, function () use ($user, $allowed, $denied) {
        foreach ($allowed as $permission) {
            expect($user->can($permission->value))->toBeTrue("{$permission->value} should be allowed");
        }
        foreach ($denied as $permission) {
            expect($user->can($permission->value))->toBeFalse("{$permission->value} should be denied");
        }
    });
})->with([
    'front desk' => [
        RoleName::FrontDesk,
        [PermissionName::CustomerCreate, PermissionName::WorkOrderAssign, PermissionName::PaymentCreate, PermissionName::PurchaseCreate],
        [PermissionName::InvoiceVoid, PermissionName::InvoiceDiscount, PermissionName::CashTransfer, PermissionName::MechanicViewIdentity, PermissionName::DashboardViewFinancials, PermissionName::UserCreate],
    ],
    'finance' => [
        RoleName::Finance,
        [PermissionName::CustomerCreate, PermissionName::InvoiceVoid, PermissionName::SupplierPaymentCreate, PermissionName::CashAdjust, PermissionName::MechanicViewIdentity, PermissionName::MechanicCreate, PermissionName::MechanicUpdate, PermissionName::DashboardViewFinancials],
        [PermissionName::UserCreate, PermissionName::RoleUpdate, PermissionName::SettingsUpdate, PermissionName::BillingManage, PermissionName::MechanicDelete],
    ],
    'warehouse' => [
        RoleName::Warehouse,
        [PermissionName::PartUpdate, PermissionName::StockAdjust, PermissionName::PurchaseCreate],
        [PermissionName::InvoiceCreate, PermissionName::PaymentCreate, PermissionName::CustomerView],
    ],
    'branch manager' => [
        RoleName::BranchManager,
        [PermissionName::InvoiceVoid, PermissionName::UserCreate, PermissionName::CashAdjust],
        [PermissionName::BillingManage, PermissionName::RoleUpdate, PermissionName::SettingsUpdate, PermissionName::BranchCreate],
    ],
]);

it('gives the owner every permission', function () {
    $shop = provisionTenant();

    inTenant($shop->tenant, $shop->branch, function () use ($shop) {
        expect($shop->owner->getAllPermissions()->pluck('name')->sort()->values()->all())
            ->toBe(collect(PermissionName::values())->sort()->values()->all());
    });
});

it('does not apply a role from one tenant inside another tenant', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    // The owner of A has no role at all in the team of tenant B.
    $roles = inTenant($b->tenant, null, fn () => $a->owner->fresh()?->getRoleNames()->all());

    expect($roles)->toBe([]);
});

it('shares the user permissions with the frontend', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('customer.create')
                && ! collect($permissions)->contains('invoice.void')));
});

it('protects the owner role from being deleted or renamed', function () {
    $shop = provisionTenant();
    $owner = Role::query()->forTenant($shop->tenant)->where('name', RoleName::Owner->value)->firstOrFail();

    expect(fn () => $owner->delete())->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $owner->update(['name' => 'boss']))->toThrow(BusinessRuleViolation::class);
});
