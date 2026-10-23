<?php

use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Services\Users\UserService;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('logs a user in and records the login time', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);

    $this->post(route('login'), ['email' => $user->email, 'password' => TEST_PASSWORD])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()?->last_login_at)->not->toBeNull();
});

it('rejects a wrong password', function () {
    $shop = provisionTenant();

    $this->post(route('login'), ['email' => $shop->owner->email, 'password' => 'wrong-password1'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects inactive users with the same message as a wrong password', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk, attributes: ['is_active' => false]);

    $this->post(route('login'), ['email' => $user->email, 'password' => TEST_PASSWORD])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('rejects users of a suspended tenant', function () {
    $shop = provisionTenant();
    $shop->tenant->update(['status' => TenantStatus::Suspended]);

    $this->post(route('login'), ['email' => $shop->owner->email, 'password' => TEST_PASSWORD])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects deleted users', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);
    inTenant($shop->tenant, null, fn () => app(UserService::class)->delete($user));

    $this->post(route('login'), ['email' => $user->email, 'password' => TEST_PASSWORD])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks the login after five failed attempts', function () {
    $shop = provisionTenant();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => $shop->owner->email, 'password' => 'wrong-password1']);
    }

    $this->post(route('login'), ['email' => $shop->owner->email, 'password' => TEST_PASSWORD])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out a user that was deactivated, on their next request', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->saveQuietly();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('has no public sign-up', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
