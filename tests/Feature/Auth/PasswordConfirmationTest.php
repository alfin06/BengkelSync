<?php

use Inertia\Testing\AssertableInertia as Assert;

it('shows the confirm password page', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ConfirmPassword'));
});

it('confirms the password', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('password.confirm'), ['password' => TEST_PASSWORD])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('rejects a wrong password', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('password.confirm'), ['password' => 'wrong-password1'])
        ->assertSessionHasErrors('password');
});
