<?php

use App\Enums\RoleName;
use App\Models\Supplier;
use Inertia\Testing\AssertableInertia as Assert;

function validSupplier(array $overrides = []): array
{
    return [
        'name' => 'Toko Sumber Jaya',
        'phone' => '024 7654321',
        'whatsapp_same_as_phone' => false,
        'whatsapp' => '0812 1111 2222',
        'sales_name' => 'Pak Andi',
        'email' => null,
        'address' => 'Jl. Pemuda 10, Semarang',
        'npwp' => null,
        'notes' => null,
        ...$overrides,
    ];
}

it('adds, lists, searches, updates and deletes suppliers', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)->post(route('suppliers.store'), validSupplier())->assertSessionHasNoErrors();
    $supplier = inTenant($shop->tenant, null, fn () => Supplier::query()->firstOrFail());

    expect($supplier->phone)->toBe('+62247654321')->and($supplier->whatsapp)->toBe('+6281211112222');

    $this->actingAs($shop->owner)->get(route('suppliers.index', ['search' => 'andi']))
        ->assertInertia(fn (Assert $page) => $page->where('suppliers.total', 1)->where('suppliers.data.0.phone', '0247654321'));

    $this->actingAs($shop->owner)->put(route('suppliers.update', $supplier), validSupplier(['name' => 'Toko Baru']))->assertSessionHasNoErrors();
    expect($supplier->fresh()?->name)->toBe('Toko Baru');

    $this->actingAs($shop->owner)->delete(route('suppliers.destroy', $supplier))->assertSessionHas('success');
    expect(inTenant($shop->tenant, null, fn () => Supplier::withTrashed()->find($supplier->id)?->trashed()))->toBeTrue();
});

it('applies supplier permissions and isolation', function () {
    $shop = provisionTenant();
    $supplier = inTenant($shop->tenant, null, fn () => Supplier::factory()->create());
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($frontDesk)->post(route('suppliers.store'), validSupplier())->assertSessionHasNoErrors();
    $this->actingAs($frontDesk)->put(route('suppliers.update', $supplier), validSupplier())->assertForbidden();
    $this->actingAs($frontDesk)->delete(route('suppliers.destroy', $supplier))->assertForbidden();
    $this->actingAs(provisionTenant('Bengkel Lain')->owner)->put(route('suppliers.update', $supplier), validSupplier())->assertNotFound();
});
