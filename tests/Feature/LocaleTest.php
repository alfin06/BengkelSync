<?php

use App\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;

it('uses Indonesian by default', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'id'));
});

it('lets guests switch the language', function () {
    $this->from(route('login'))
        ->post(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('rejects unsupported languages', function () {
    $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

it("uses the user's language, then the tenant default", function () {
    $shop = provisionTenant();
    $english = userWithRole($shop, RoleName::FrontDesk, attributes: ['locale' => 'en']);
    $default = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($english)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

    $this->actingAs($default)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'id'));
});

it('translates validation messages', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->patch(route('profile.update'), ['name' => '', 'locale' => 'id'])
        ->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);
});
