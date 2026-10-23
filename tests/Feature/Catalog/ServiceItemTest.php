<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\ServiceItem;
use App\Services\Tenancy\ProvisionedTenant;
use Inertia\Testing\AssertableInertia as Assert;

function serviceIn(ProvisionedTenant $shop, array $attributes = []): ServiceItem
{
    return inTenant($shop->tenant, $shop->branch, fn () => ServiceItem::factory()->create($attributes));
}

function validService(array $overrides = []): array
{
    return [
        'code' => 'go-01',
        'name' => 'Ganti oli mesin',
        'category' => 'Perawatan',
        'default_price' => 50000,
        'estimated_minutes' => 30,
        'is_active' => true,
        ...$overrides,
    ];
}

it('lists active services grouped by category, then name', function () {
    $shop = provisionTenant();
    serviceIn($shop, ['name' => 'Spooring', 'category' => 'Kaki-kaki']);
    serviceIn($shop, ['name' => 'Tune up', 'category' => null]);
    serviceIn($shop, ['name' => 'Balancing', 'category' => 'Kaki-kaki']);
    serviceIn($shop, ['name' => 'Lama', 'category' => 'Kaki-kaki', 'is_active' => false]);

    $this->actingAs($shop->owner)->get(route('service-items.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ServiceItems/Index')
            ->where('items.total', 3)
            ->where('items.data.0.name', 'Balancing')
            ->where('items.data.1.name', 'Spooring')
            ->where('items.data.2.name', 'Tune up')
            ->where('categories', ['Kaki-kaki']));
});

it('filters by status and searches name, code and category', function () {
    $shop = provisionTenant();
    serviceIn($shop, ['name' => 'Ganti oli', 'code' => 'GO-01', 'category' => 'Perawatan']);
    serviceIn($shop, ['name' => 'Spooring', 'category' => 'Kaki-kaki']);
    serviceIn($shop, ['name' => 'Lama', 'is_active' => false]);

    $totals = fn (array $query) => $this->actingAs($shop->owner)
        ->get(route('service-items.index', $query))
        ->viewData('page')['props']['items']['total'];

    expect($totals(['status' => 'inactive']))->toBe(1)
        ->and($totals(['status' => 'all']))->toBe(3)
        ->and($totals(['search' => 'go-0']))->toBe(1)
        ->and($totals(['search' => 'kaki']))->toBe(1);
});

it('adds a service with an integer price and an uppercase code', function () {
    $shop = provisionTenant();
    $finance = userWithRole($shop, RoleName::Finance);

    $this->actingAs($finance)->post(route('service-items.store'), validService())->assertSessionHasNoErrors();

    $item = inTenant($shop->tenant, null, fn () => ServiceItem::query()->firstOrFail());

    expect($item->code)->toBe('GO-01')
        ->and($item->default_price)->toBe(50000)
        ->and($item->tenant_id)->toBe($shop->tenant->id);
});

it('validates the input', function (array $overrides, string $field) {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('service-items.store'), validService($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'price with cents' => [['default_price' => 50000.5], 'default_price'],
    'negative price' => [['default_price' => -1], 'default_price'],
    'code with spaces' => [['code' => 'GO 01'], 'code'],
    'zero minutes' => [['estimated_minutes' => 0], 'estimated_minutes'],
]);

it('keeps codes unique within a tenant, but not across tenants', function () {
    $shop = provisionTenant();
    serviceIn($shop, ['code' => 'GO-01']);
    $other = provisionTenant('Bengkel Lain');

    $this->actingAs($shop->owner)->post(route('service-items.store'), validService(['code' => 'go-01']))->assertSessionHasErrors('code');
    $this->actingAs($other->owner)->post(route('service-items.store'), validService())->assertSessionHasNoErrors();
});

it('allows several services without a code', function () {
    $shop = provisionTenant();
    serviceIn($shop, ['code' => null]);

    $this->actingAs($shop->owner)->post(route('service-items.store'), validService(['code' => '']))->assertSessionHasNoErrors();
});

it('updates the price and audits the change', function () {
    $shop = provisionTenant();
    $item = serviceIn($shop, ['code' => 'GO-01', 'default_price' => 50000]);

    $this->actingAs($shop->owner)
        ->put(route('service-items.update', $item), validService(['default_price' => 55000]))
        ->assertSessionHasNoErrors();

    $log = inTenant($shop->tenant, null, fn () => AuditLog::query()
        ->where('auditable_id', $item->id)->where('action', 'updated')->firstOrFail());

    expect($item->fresh()?->default_price)->toBe(55000)
        ->and($log->old_values)->toMatchArray(['default_price' => 50000])
        ->and($log->new_values)->toMatchArray(['default_price' => 55000]);
});

it('deactivates a service', function () {
    $shop = provisionTenant();
    $item = serviceIn($shop);

    $this->actingAs($shop->owner)->put(route('service-items.update', $item), validService(['is_active' => false]));

    expect($item->fresh()?->is_active)->toBeFalse();
});

it('deletes a service that was never used', function () {
    $shop = provisionTenant();
    $item = serviceIn($shop);

    $this->actingAs($shop->owner)->delete(route('service-items.destroy', $item))->assertSessionHas('success');

    expect(inTenant($shop->tenant, null, fn () => ServiceItem::query()->find($item->id)))->toBeNull();
});

it('applies the role permissions', function () {
    $shop = provisionTenant();
    $item = serviceIn($shop);
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);
    $finance = userWithRole($shop, RoleName::Finance);
    $warehouse = userWithRole($shop, RoleName::Warehouse);

    $this->actingAs($frontDesk)->get(route('service-items.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can', ['create' => false, 'update' => false, 'delete' => false]));
    $this->actingAs($frontDesk)->post(route('service-items.store'), validService())->assertForbidden();
    $this->actingAs($frontDesk)->put(route('service-items.update', $item), validService())->assertForbidden();

    $this->actingAs($finance)->put(route('service-items.update', $item), validService())->assertSessionHasNoErrors();
    $this->actingAs($finance)->delete(route('service-items.destroy', $item))->assertForbidden();

    $this->actingAs($warehouse)->get(route('service-items.index'))->assertForbidden();
});

it('returns 404 for a service of another tenant', function () {
    $shop = provisionTenant();
    $foreign = serviceIn(provisionTenant('Bengkel Lain'));

    $this->actingAs($shop->owner)->put(route('service-items.update', $foreign), validService())->assertNotFound();
    $this->actingAs($shop->owner)->delete(route('service-items.destroy', $foreign))->assertNotFound();
});
