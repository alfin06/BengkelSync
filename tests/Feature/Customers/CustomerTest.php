<?php

use App\Enums\RoleName;
use App\Models\Customer;
use Inertia\Testing\AssertableInertia as Assert;

function validCustomer(array $overrides = []): array
{
    return [
        'type' => 'individual',
        'name' => 'Budi Santoso',
        'phone' => '0812-3456-7890',
        'whatsapp_same_as_phone' => true,
        'whatsapp' => null,
        'email' => null,
        'address' => 'Jl. Pemuda 1, Semarang',
        'npwp' => null,
        'notes' => null,
        ...$overrides,
    ];
}

describe('list and search', function () {
    it('lists customers of the tenant, paginated and sorted by name', function () {
        $shop = provisionTenant();
        customerIn($shop, ['name' => 'Zainal']);
        customerIn($shop, ['name' => 'Agus']);
        $other = provisionTenant('Bengkel Lain');
        customerIn($other, ['name' => 'Bukan Pelanggan Kami']);

        $this->actingAs($shop->owner)->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Index')
                ->where('customers.total', 2)
                ->where('customers.data.0.name', 'Agus')
                ->where('customers.data.1.name', 'Zainal'));
    });

    it('finds customers by name, by phone typed in any format, and by plate', function (string $term) {
        $shop = provisionTenant();
        $budi = customerIn($shop, ['name' => 'Budi Santoso', 'phone' => '+6281234567890']);
        vehicleFor($shop, $budi, ['plate_number' => 'H 1234 AB']);
        customerIn($shop, ['name' => 'Siti', 'phone' => '+6285700000000']);

        $this->actingAs($shop->owner)->get(route('customers.index', ['search' => $term]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.total', 1)
                ->where('customers.data.0.name', 'Budi Santoso'));
    })->with([
        'name' => 'budi',
        'local phone' => '0812-3456',
        'international phone' => '+62 812 3456 7890',
        'plate without spaces' => 'h1234ab',
        'partial plate' => 'H 1234',
    ]);
});

describe('create, update and delete', function () {
    it('creates a customer with the phone stored as +62 and WhatsApp same as phone', function () {
        $shop = provisionTenant();

        $response = $this->actingAs($shop->owner)->post(route('customers.store'), validCustomer());

        $customer = inTenant($shop->tenant, null, fn () => Customer::query()->firstOrFail());
        $response->assertRedirect(route('customers.show', $customer));

        expect($customer->phone)->toBe('+6281234567890')
            ->and($customer->whatsapp)->toBe('+6281234567890')
            ->and($customer->tenant_id)->toBe($shop->tenant->id);
    });

    it('only needs a name', function () {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)
            ->post(route('customers.store'), validCustomer(['phone' => null, 'address' => null]))
            ->assertSessionHasNoErrors();
    });

    it('validates the input', function (array $overrides, string $field) {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)
            ->post(route('customers.store'), validCustomer($overrides))
            ->assertSessionHasErrors($field);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'invalid phone' => [['phone' => '12345'], 'phone'],
        'invalid separate whatsapp' => [['whatsapp_same_as_phone' => false, 'whatsapp' => 'abc'], 'whatsapp'],
        'unknown type' => [['type' => 'alien'], 'type'],
        'bad email' => [['email' => 'not-an-email'], 'email'],
    ]);

    it('keeps an NPWP only for companies', function () {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)->post(route('customers.store'), validCustomer(['npwp' => '01.234.567.8-901.000']));
        $this->actingAs($shop->owner)->post(route('customers.store'), validCustomer(['name' => 'PT Maju', 'type' => 'company', 'npwp' => '01.234.567.8-901.000']));

        $npwps = inTenant($shop->tenant, null, fn () => Customer::query()->orderBy('name')->pluck('npwp', 'name')->all());

        expect($npwps)->toBe(['Budi Santoso' => null, 'PT Maju' => '01.234.567.8-901.000']);
    });

    it('updates a customer', function () {
        $shop = provisionTenant();
        $customer = customerIn($shop);

        $this->actingAs($shop->owner)
            ->put(route('customers.update', $customer), validCustomer(['name' => 'Nama Baru', 'whatsapp_same_as_phone' => false, 'whatsapp' => '0857 1111 2222']))
            ->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        expect($customer->name)->toBe('Nama Baru')->and($customer->whatsapp)->toBe('+6285711112222');
    });

    it('does not delete a customer who still owns vehicles', function () {
        $shop = provisionTenant();
        $customer = customerIn($shop);
        vehicleFor($shop, $customer);

        $this->actingAs($shop->owner)
            ->from(route('customers.show', $customer))
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('error', __('customers.delete_has_vehicles'));

        expect($customer->fresh()?->trashed())->toBeFalse();
    });

    it('soft-deletes a customer without vehicles', function () {
        $shop = provisionTenant();
        $customer = customerIn($shop);

        $this->actingAs($shop->owner)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        expect(inTenant($shop->tenant, null, fn () => Customer::withTrashed()->find($customer->id)?->trashed()))->toBeTrue();
    });
});

describe('phone duplicates', function () {
    it('lists other customers that use the same phone', function () {
        $shop = provisionTenant();
        $existing = customerIn($shop, ['name' => 'Ayah Budi', 'phone' => '+6281234567890']);

        $this->actingAs($shop->owner)
            ->getJson(route('customers.lookup.phone', ['phone' => '0812-3456-7890']))
            ->assertOk()
            ->assertJsonPath('0.name', 'Ayah Budi');

        $this->actingAs($shop->owner)
            ->getJson(route('customers.lookup.phone', ['phone' => '0812-3456-7890', 'except' => $existing->id]))
            ->assertExactJson([]);
    });

    it('allows two customers with the same phone', function () {
        $shop = provisionTenant();
        customerIn($shop, ['phone' => '+6281234567890']);

        $this->actingAs($shop->owner)
            ->post(route('customers.store'), validCustomer())
            ->assertSessionHasNoErrors();
    });
});

describe('permissions and isolation', function () {
    it('does not let warehouse staff see customers', function () {
        $shop = provisionTenant();
        $warehouse = userWithRole($shop, RoleName::Warehouse);

        $this->actingAs($warehouse)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($warehouse)->getJson(route('customers.lookup.search', ['q' => 'bu']))->assertForbidden();
    });

    it('does not let front desk staff delete customers', function () {
        $shop = provisionTenant();
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $customer = customerIn($shop);

        $this->actingAs($frontDesk)->delete(route('customers.destroy', $customer))->assertForbidden();
        $this->actingAs($frontDesk)->post(route('customers.store'), validCustomer())->assertRedirect();
    });

    it('returns 404 for a customer of another tenant', function () {
        $shop = provisionTenant();
        $other = provisionTenant('Bengkel Lain');
        $foreign = customerIn($other);

        $this->actingAs($shop->owner)->get(route('customers.show', $foreign))->assertNotFound();
        $this->actingAs($shop->owner)->put(route('customers.update', $foreign), validCustomer())->assertNotFound();
        $this->actingAs($shop->owner)->delete(route('customers.destroy', $foreign))->assertNotFound();
    });

    it('shows a customer with their vehicles and what the user may do', function () {
        $shop = provisionTenant();
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $customer = customerIn($shop, ['phone' => '+6281234567890', 'whatsapp' => '+6281234567890']);
        vehicleFor($shop, $customer, ['plate_number' => 'B 1 AA']);

        $this->actingAs($frontDesk)->get(route('customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Show')
                ->where('customer.phone', '0812-3456-7890')
                ->where('customer.whatsapp_link', 'https://wa.me/6281234567890')
                ->where('customer.vehicles.0.plate_number', 'B 1 AA')
                ->where('can.update', true)
                ->where('can.delete', false));
    });
});
