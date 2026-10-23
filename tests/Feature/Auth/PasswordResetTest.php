<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the forgot password page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
});

it('sends a reset link to a registered email', function () {
    Notification::fake();
    $shop = provisionTenant();

    $this->post(route('password.email'), ['email' => $shop->owner->email])
        ->assertSessionHas('status', __('passwords.sent_if_exists'));

    Notification::assertSentTo($shop->owner, ResetPassword::class);
});

it('answers the same for an unknown email, so accounts cannot be discovered', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nobody@example.test'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent_if_exists'));

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $shop = provisionTenant();

    $this->post(route('password.email'), ['email' => $shop->owner->email]);

    Notification::assertSentTo($shop->owner, ResetPassword::class, function (ResetPassword $notification) use ($shop) {
        $this->get(route('password.reset', $notification->token))
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword'));

        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $shop->owner->email,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('newpassword1', (string) $shop->owner->fresh()?->password))->toBeTrue();
});

it('rejects a weak new password', function () {
    $shop = provisionTenant();

    $this->post(route('password.store'), [
        'token' => 'whatever',
        'email' => $shop->owner->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});
