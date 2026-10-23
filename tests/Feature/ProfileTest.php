<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the profile page', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profile.email', $shop->owner->email)
            ->where('locales', ['id', 'en']));
});

it('updates name, phone and language, storing the phone as +62', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Budi Santoso', 'phone' => '0812-3456-7890', 'locale' => 'en'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->name)->toBe('Budi Santoso')
        ->and($user->phone)->toBe('+6281234567890')
        ->and($user->locale)->toBe('en');
});

it('rejects an invalid phone number and an unsupported language', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->patch(route('profile.update'), ['name' => 'X', 'phone' => '12', 'locale' => 'fr'])
        ->assertSessionHasErrors(['phone', 'locale']);
});

it('never changes email or role from the profile', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);
    $email = $user->email;

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Budi',
        'locale' => 'id',
        'email' => 'hacker@example.test',
        'is_active' => false,
        'all_branches' => true,
        'tenant_id' => 999,
    ]);

    $user->refresh();

    expect($user->email)->toBe($email)
        ->and($user->is_active)->toBeTrue()
        ->and($user->all_branches)->toBeFalse()
        ->and($user->tenant_id)->toBe($shop->tenant->id);
});

it('has no self-delete', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)->delete('/profile')->assertMethodNotAllowed();
});

it('changes the password when the current one is correct, and audits it', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->put(route('password.update'), [
            'current_password' => TEST_PASSWORD,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('newpassword1', (string) $shop->owner->fresh()?->password))->toBeTrue();

    $log = inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'password_changed')->first());

    expect($log)->not->toBeNull()
        ->and($log?->auditable_id)->toBe($shop->owner->id)
        ->and(json_encode($log?->getAttributes()))->not->toContain('newpassword1');
});

it('does not change the password when the current one is wrong', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->put(route('password.update'), [
            'current_password' => 'wrong-password1',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
        ->assertSessionHasErrors('current_password');
});
