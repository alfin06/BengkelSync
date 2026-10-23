<?php

use App\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;

it('picks the only branch of a single-branch user', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('branch.id', $shop->branch->id)
            ->has('branches', 1));
});

it('lets a user switch to an assigned branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop, 'Cabang Timur');
    $user = userWithRole($shop, RoleName::BranchManager, [$shop->branch, $second]);

    $this->actingAs($user)
        ->post(route('branch.switch'), ['branch_id' => $second->id])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('branch.id', $second->id)->has('branches', 2));
});

it('does not let a user switch to a branch they are not assigned to', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);
    $user = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($user)
        ->post(route('branch.switch'), ['branch_id' => $second->id])
        ->assertForbidden();
});

it('does not let a user switch to a branch of another tenant', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    $this->actingAs($a->owner)
        ->post(route('branch.switch'), ['branch_id' => $b->branch->id])
        ->assertForbidden();
});

it('ignores a branch id in the session that the user may no longer use', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);
    $user = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($user)
        ->withSession(['active_branch_id' => $second->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('branch.id', $shop->branch->id));
});

it('gives owners with "all branches" access to every branch, starting in the main branch', function () {
    $shop = provisionTenant();
    addBranch($shop, 'Abadi');

    $this->actingAs($shop->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('branches', 2)->where('branch.id', $shop->branch->id));
});

it('blocks users without any branch', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk, []);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
});
