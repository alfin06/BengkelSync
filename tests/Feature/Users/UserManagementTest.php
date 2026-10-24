<?php

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy\ProvisionedTenant;
use App\Services\Users\RoleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function roleOf(ProvisionedTenant $shop, RoleName|string $name): Role
{
    return Role::query()->forTenant($shop->tenant)->where('name', $name instanceof RoleName ? $name->value : $name)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function staffInput(ProvisionedTenant $shop, array $overrides = []): array
{
    return [
        'name' => 'Sari Admin',
        'email' => 'sari@example.test',
        'phone' => '0812 1111 2222',
        'locale' => 'id',
        'password' => 'rahasia123',
        'role_id' => roleOf($shop, RoleName::FrontDesk)->id,
        'all_branches' => false,
        'branch_ids' => [$shop->branch->id],
        ...$overrides,
    ];
}

function roleService(ProvisionedTenant $shop, callable $callback): mixed
{
    return inTenant($shop->tenant, $shop->branch, fn () => $callback(app(RoleService::class)));
}

describe('staff accounts', function () {
    it('lets the owner add a staff account with a password, role and branch', function () {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)->post(route('settings.users.store'), staffInput($shop))->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'sari@example.test')->sole();
        expect($user->tenant_id)->toBe($shop->tenant->id)
            ->and(Hash::check('rahasia123', $user->password))->toBeTrue()
            ->and($user->phone)->toBe('+6281211112222')
            ->and(inTenant($shop->tenant, $shop->branch, fn () => $user->fresh()?->hasRole(RoleName::FrontDesk->value)))->toBeTrue()
            ->and(inTenant($shop->tenant, $shop->branch, fn () => $user->branches()->pluck('branches.id')->all()))->toBe([$shop->branch->id]);
    });

    it('keeps one email for one account across all shops', function () {
        $shop = provisionTenant();
        $other = provisionTenant('Bengkel Lain');
        userWithRole($other, RoleName::FrontDesk, attributes: ['email' => 'taken@example.test']);

        $this->actingAs($shop->owner)->post(route('settings.users.store'), staffInput($shop, ['email' => 'taken@example.test']))
            ->assertSessionHasErrors('email');
    });

    it('never lets someone hand out more than they have', function () {
        $shop = provisionTenant();
        $manager = userWithRole($shop, RoleName::BranchManager);
        $powerful = roleService($shop, fn (RoleService $roles) => $roles->create('Super', [Permission::RoleUpdate->value, Permission::CustomerView->value]));

        // The owner role, and a role with permissions the manager lacks (role.update).
        $this->actingAs($manager)->post(route('settings.users.store'), staffInput($shop, ['role_id' => roleOf($shop, RoleName::Owner)->id]))->assertSessionHasErrors('role_id');
        $this->actingAs($manager)->post(route('settings.users.store'), staffInput($shop, ['role_id' => $powerful->id]))->assertSessionHasErrors('role_id');

        // A branch the manager does not work in, and "all branches".
        $east = addBranch($shop);
        $this->actingAs($manager)->post(route('settings.users.store'), staffInput($shop, ['branch_ids' => [$east->id]]))->assertSessionHasErrors('branch_ids');
        $this->actingAs($manager)->post(route('settings.users.store'), staffInput($shop, ['all_branches' => true]))->assertSessionHasErrors('all_branches');

        // What the manager has is fine.
        $this->actingAs($manager)->post(route('settings.users.store'), staffInput($shop))->assertSessionHasNoErrors();
    });

    it('refuses a role or branch of another shop', function () {
        $shop = provisionTenant();
        $other = provisionTenant('Bengkel Lain');

        $this->actingAs($shop->owner)->post(route('settings.users.store'), staffInput($shop, ['role_id' => roleOf($other, RoleName::FrontDesk)->id]))->assertSessionHasErrors('role_id');
        $this->actingAs($shop->owner)->post(route('settings.users.store'), staffInput($shop, ['branch_ids' => [$other->branch->id]]))->assertSessionHasErrors('branch_ids');
    });

    it('protects owner accounts and your own account', function () {
        $shop = provisionTenant();
        $manager = userWithRole($shop, RoleName::BranchManager);
        $data = fn (User $user) => [...staffInput($shop), 'email' => $user->email, 'is_active' => true, 'role_id' => roleOf($shop, RoleName::FrontDesk)->id];

        $this->actingAs($manager)->put(route('settings.users.update', $shop->owner), $data($shop->owner))->assertForbidden();
        $this->actingAs($manager)->put(route('settings.users.update', $manager), $data($manager))->assertForbidden();
        $this->actingAs($shop->owner)->put(route('settings.users.update', $shop->owner), $data($shop->owner))->assertForbidden();
        $this->actingAs($manager)->delete(route('settings.users.destroy', $shop->owner))->assertForbidden();
    });

    it('changes role and branches, and logs someone out when deactivated', function () {
        $shop = provisionTenant();
        $staff = userWithRole($shop, RoleName::FrontDesk);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($shop->owner)->put(route('settings.users.update', $staff), [
            ...staffInput($shop),
            'email' => $staff->email,
            'role_id' => roleOf($shop, RoleName::Finance)->id,
            'is_active' => false,
        ])->assertSessionHasNoErrors();

        expect($staff->fresh()?->is_active)->toBeFalse()
            ->and(inTenant($shop->tenant, $shop->branch, fn () => $staff->fresh()?->hasRole(RoleName::Finance->value)))->toBeTrue()
            ->and(DB::table('sessions')->where('user_id', $staff->id)->exists())->toBeFalse()
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'role_changed')->exists()))->toBeTrue();
    });

    it('sets a new password for someone who forgot theirs', function () {
        $shop = provisionTenant();
        $staff = userWithRole($shop, RoleName::FrontDesk);
        DB::table('sessions')->insert(['id' => 'xyz', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($shop->owner)->put(route('settings.users.password', $staff), ['password' => 'pendek'])->assertSessionHasErrors('password');
        $this->actingAs($shop->owner)->put(route('settings.users.password', $staff), ['password' => 'baru12345'])->assertSessionHasNoErrors();

        expect(Hash::check('baru12345', (string) $staff->fresh()?->password))->toBeTrue()
            ->and(DB::table('sessions')->where('user_id', $staff->id)->exists())->toBeFalse();
    });

    it('keeps the user list for those allowed, and only shows roles they may give', function () {
        $shop = provisionTenant();
        $manager = userWithRole($shop, RoleName::BranchManager);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('settings.users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('settings.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Users')
                ->where('roles', fn ($roles) => ! collect($roles)->contains('label', RoleName::Owner->label()))
                ->where('can.roles', false));
        $this->actingAs(provisionTenant('Bengkel Lain')->owner)->put(route('settings.users.update', $manager), staffInput($shop))->assertNotFound();
    });
});

describe('roles and the permission matrix', function () {
    it('lets the owner make a custom role and change what a role may do', function () {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)->post(route('settings.roles.store'), [
            'name' => 'Kasir',
            'permissions' => [Permission::InvoiceView->value, Permission::PaymentCreate->value, Permission::BillingManage->value],
        ])->assertSessionHasNoErrors();

        $kasir = roleOf($shop, 'Kasir');
        expect($kasir->permissions()->pluck('name')->sort()->values()->all())->toBe([Permission::InvoiceView->value, Permission::PaymentCreate->value]);

        // Front Desk loses customer.create; a Front Desk user notices on the next request.
        $frontDesk = roleOf($shop, RoleName::FrontDesk);
        $staff = userWithRole($shop, RoleName::FrontDesk);
        $kept = $frontDesk->permissions()->pluck('name')->reject(fn ($name) => $name === Permission::CustomerCreate->value)->values()->all();

        $this->actingAs($shop->owner)->put(route('settings.roles.update', $frontDesk), ['permissions' => $kept])->assertSessionHasNoErrors();

        expect(inTenant($shop->tenant, $shop->branch, fn () => $staff->fresh()?->can(Permission::CustomerCreate->value)))->toBeFalse()
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'role_updated')->exists()))->toBeTrue();
    });

    it('never changes the owner role, deletes system roles or roles in use', function () {
        $shop = provisionTenant();
        $used = roleService($shop, fn (RoleService $roles) => $roles->create('Montir senior', []));
        $holder = userWithRole($shop, RoleName::FrontDesk);
        inTenant($shop->tenant, $shop->branch, fn () => $holder->syncRoles([$used->name]));

        $this->actingAs($shop->owner)->put(route('settings.roles.update', roleOf($shop, RoleName::Owner)), ['permissions' => []])->assertForbidden();
        expect(fn () => roleService($shop, fn (RoleService $roles) => $roles->delete(roleOf($shop, RoleName::Finance))))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => roleService($shop, fn (RoleService $roles) => $roles->delete($used)))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => roleService($shop, fn (RoleService $roles) => $roles->create('Pemilik', [])))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => roleService($shop, fn (RoleService $roles) => $roles->create('montir SENIOR', [])))->toThrow(BusinessRuleViolation::class);
    });

    it('keeps the matrix to the owner and to the shop', function () {
        $shop = provisionTenant();
        $other = provisionTenant('Bengkel Lain');

        $this->actingAs(userWithRole($shop, RoleName::BranchManager))->get(route('settings.roles.index'))->assertForbidden();
        $this->actingAs($shop->owner)->get(route('settings.roles.edit', roleOf($other, RoleName::FrontDesk)))->assertNotFound();
        $this->actingAs($shop->owner)->get(route('settings.roles.edit', roleOf($shop, RoleName::FrontDesk)))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/RoleMatrix')
                ->where('matrix.0.resource', 'customer')
                ->where('matrix.0.standard.view', 'customer.view'));
    });
});
