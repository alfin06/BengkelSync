<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Mechanic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(config('filesystems.private_disk'));
});

function validMechanic(array $overrides = []): array
{
    return ['name' => 'Joko Susilo', 'phone' => '0812 3456 7890', 'nik' => '3374 0101 0101 0001', 'notes' => null, 'is_active' => true, ...$overrides];
}

it('adds a mechanic to the active branch with the NIK encrypted at rest', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)->post(route('mechanics.store'), validMechanic())->assertSessionHasNoErrors();

    $mechanic = inTenant($shop->tenant, $shop->branch, fn () => Mechanic::query()->firstOrFail());
    $stored = DB::table('mechanics')->where('id', $mechanic->id)->value('nik');

    expect($mechanic->branch_id)->toBe($shop->branch->id)
        ->and($mechanic->phone)->toBe('+6281234567890')
        ->and($mechanic->nik)->toBe('3374010101010001')
        ->and($stored)->not->toContain('3374010101010001');
});

it('rejects an invalid NIK', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('mechanics.store'), validMechanic(['nik' => '12345']))
        ->assertSessionHasErrors(['nik' => 'NIK harus 16 digit angka.']);
});

it('never writes the NIK into the audit log', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)->post(route('mechanics.store'), validMechanic());

    $log = inTenant($shop->tenant, null, fn () => AuditLog::query()->where('auditable_type', (new Mechanic)->getMorphClass())->firstOrFail());

    expect($log->new_values)->toHaveKey('name')->not->toHaveKey('nik')->not->toHaveKey('ktp_path');
});

it('stores the KTP photo on the private disk and serves it only to permitted users', function () {
    $shop = provisionTenant();
    $finance = userWithRole($shop, RoleName::Finance);
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($shop->owner)
        ->post(route('mechanics.store'), validMechanic(['ktp' => UploadedFile::fake()->image('ktp.jpg')]))
        ->assertSessionHasNoErrors();

    $mechanic = inTenant($shop->tenant, $shop->branch, fn () => Mechanic::query()->firstOrFail());

    expect($mechanic->ktp_path)->toStartWith("tenants/{$shop->tenant->id}/mechanics/")
        ->and($mechanic->ktp_path)->not->toContain('ktp.jpg');
    Storage::disk(config('filesystems.private_disk'))->assertExists($mechanic->ktp_path);

    $this->actingAs($finance)->get(route('mechanics.ktp', $mechanic))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    $this->actingAs($frontDesk)->get(route('mechanics.ktp', $mechanic))->assertForbidden();
});

it('rejects files that are not images or too large', function (UploadedFile $file) {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('mechanics.store'), validMechanic(['ktp' => $file]))
        ->assertSessionHasErrors('ktp');
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
    'too large' => fn () => UploadedFile::fake()->image('ktp.jpg')->size(6000),
]);

it('replaces and removes the KTP photo, deleting the old file', function () {
    $shop = provisionTenant();
    $disk = Storage::disk(config('filesystems.private_disk'));

    $this->actingAs($shop->owner)->post(route('mechanics.store'), validMechanic(['ktp' => UploadedFile::fake()->image('a.jpg')]));
    $mechanic = inTenant($shop->tenant, $shop->branch, fn () => Mechanic::query()->firstOrFail());
    $first = $mechanic->ktp_path;

    $this->actingAs($shop->owner)
        ->post(route('mechanics.update', $mechanic), validMechanic(['_method' => 'put', 'ktp' => UploadedFile::fake()->image('b.png')]))
        ->assertSessionHasNoErrors();
    $second = $mechanic->fresh()?->ktp_path;

    expect($second)->not->toBe($first);
    $disk->assertMissing((string) $first);
    $disk->assertExists((string) $second);

    $this->actingAs($shop->owner)->put(route('mechanics.update', $mechanic), validMechanic(['remove_ktp' => true]));

    expect($mechanic->fresh()?->ktp_path)->toBeNull();
    $disk->assertMissing((string) $second);
});

it('masks the NIK and hides identity fields from front desk staff', function () {
    $shop = provisionTenant();
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);
    $mechanic = mechanicIn($shop, attributes: ['nik' => '3374010101010001']);

    $this->actingAs($frontDesk)->get(route('mechanics.show', $mechanic))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mechanic.nik', null)
            ->where('mechanic.nik_masked', '3374••••••••0001')
            ->where('mechanic.ktp_url', null)
            ->where('can.identity', false));

    $this->actingAs($shop->owner)->get(route('mechanics.show', $mechanic))
        ->assertInertia(fn (Assert $page) => $page->where('mechanic.nik', '3374010101010001'));
});

it('does not let users without identity permission send a NIK or photo', function () {
    $shop = provisionTenant();
    $manager = userWithRole($shop, RoleName::BranchManager);
    $mechanic = mechanicIn($shop);

    // A customized role that may edit mechanics but not their identity.
    inTenant($shop->tenant, null, fn () => $manager->roles()->first()?->revokePermissionTo('mechanic.view_identity'));

    $this->actingAs($manager)
        ->put(route('mechanics.update', $mechanic), validMechanic())
        ->assertSessionHasErrors('nik');

    $this->actingAs($manager)
        ->put(route('mechanics.update', $mechanic), ['name' => 'Nama Baru', 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($mechanic->fresh()?->nik)->toBe($mechanic->nik);
});

it('only shows mechanics of the active branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop, 'Cabang Timur');
    mechanicIn($shop, attributes: ['name' => 'Di Pusat']);
    $elsewhere = mechanicIn($shop, $second, ['name' => 'Di Timur']);

    $this->actingAs($shop->owner)->get(route('mechanics.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mechanics.total', 1)
            ->where('mechanics.data.0.name', 'Di Pusat'));

    $this->actingAs($shop->owner)->get(route('mechanics.show', $elsewhere))->assertNotFound();
});

it('filters by status and searches by name', function () {
    $shop = provisionTenant();
    mechanicIn($shop, attributes: ['name' => 'Joko']);
    mechanicIn($shop, attributes: ['name' => 'Budi', 'is_active' => false]);

    $total = fn (array $query) => $this->actingAs($shop->owner)->get(route('mechanics.index', $query))->viewData('page')['props']['mechanics']['total'];

    expect($total([]))->toBe(1)
        ->and($total(['status' => 'all']))->toBe(2)
        ->and($total(['status' => 'all', 'search' => 'bud']))->toBe(1);
});

it('applies the role permissions', function () {
    $shop = provisionTenant();
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);
    $warehouse = userWithRole($shop, RoleName::Warehouse);
    $mechanic = mechanicIn($shop);

    $this->actingAs($frontDesk)->get(route('mechanics.index'))->assertOk();
    $this->actingAs($frontDesk)->post(route('mechanics.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($frontDesk)->delete(route('mechanics.destroy', $mechanic))->assertForbidden();
    $this->actingAs($warehouse)->get(route('mechanics.index'))->assertForbidden();

    $this->actingAs($shop->owner)->delete(route('mechanics.destroy', $mechanic))->assertRedirect(route('mechanics.index'));
    expect(inTenant($shop->tenant, $shop->branch, fn () => Mechanic::withTrashed()->find($mechanic->id)?->trashed()))->toBeTrue();
});

it('returns 404 for a mechanic of another tenant, including the KTP photo', function () {
    $shop = provisionTenant();
    $foreign = mechanicIn(provisionTenant('Bengkel Lain'));

    $this->actingAs($shop->owner)->get(route('mechanics.show', $foreign))->assertNotFound();
    $this->actingAs($shop->owner)->get(route('mechanics.ktp', $foreign))->assertNotFound();
});
