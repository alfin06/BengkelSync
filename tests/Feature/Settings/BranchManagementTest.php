<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Mechanic;
use App\Models\PaymentMethod;
use App\Models\WorkOrder;
use App\Services\Cash\CashService;
use App\Services\Invoicing\InvoiceService;
use App\Services\Tenancy\ProvisionedTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(config('filesystems.private_disk'));
});

/**
 * @return array<string, mixed>
 */
function branchInput(array $overrides = []): array
{
    return [
        'name' => 'Cabang Sidoarjo',
        'code' => 'sda',
        'timezone' => 'Asia/Jakarta',
        'address' => 'Jl. Raya Sidoarjo 5',
        'phone' => '031 8765432',
        'npwp' => '',
        ...$overrides,
    ];
}

/**
 * A second branch, so the main branch is not the last one and may be deleted.
 */
function secondBranch(ProvisionedTenant $shop): Branch
{
    test()->actingAs($shop->owner)->post(route('settings.branches.store'), branchInput())->assertSessionHasNoErrors();

    return inTenant($shop->tenant, null, fn () => Branch::query()->where('code', 'SDA')->sole());
}

describe('adding a branch', function () {
    it('creates the branch with its cash accounts and payment method, visible to the owner', function () {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)->post(route('settings.branches.store'), branchInput(['timezone' => 'Asia/Makassar', 'logo' => UploadedFile::fake()->image('logo.png')]))
            ->assertRedirect(route('settings.shop.edit'))
            ->assertSessionHasNoErrors();

        $branch = inTenant($shop->tenant, null, fn () => Branch::query()->where('code', 'SDA')->sole());

        expect($branch->name)->toBe('Cabang Sidoarjo')
            ->and($branch->timezone)->toBe('Asia/Makassar')
            ->and($branch->phone)->toBe('+62318765432')
            ->and($branch->cash_purchase_limit)->toBe(500_000)
            ->and(Storage::disk(config('filesystems.private_disk'))->exists((string) $branch->logo_path))->toBeTrue()
            ->and(inTenant($shop->tenant, $branch, fn () => CashAccount::query()->count()))->toBe(2)
            ->and(inTenant($shop->tenant, $branch, fn () => PaymentMethod::query()->count()))->toBe(1)
            ->and(inTenant($shop->tenant, null, fn () => $shop->owner->canAccessBranch($branch)))->toBeTrue()
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('auditable_type', $branch->getMorphClass())->where('auditable_id', $branch->id)->exists()))->toBeTrue();
    });

    it('checks the code: 2-6 letters or digits, unique in the shop including deleted branches', function () {
        $shop = provisionTenant();
        $owner = $shop->owner;

        $this->actingAs($owner)->post(route('settings.branches.store'), branchInput(['code' => 'K']))->assertSessionHasErrors('code');
        $this->actingAs($owner)->post(route('settings.branches.store'), branchInput(['code' => 'AB-1']))->assertSessionHasErrors('code');
        $this->actingAs($owner)->post(route('settings.branches.store'), branchInput(['code' => $shop->branch->code]))->assertSessionHasErrors('code');
        $this->actingAs($owner)->post(route('settings.branches.store'), branchInput(['timezone' => 'Europe/Paris']))->assertSessionHasErrors('timezone');

        // A used branch is only soft-deleted, so its code stays taken (it can be restored).
        $deleted = secondBranch($shop);
        mechanicIn($shop, $deleted);
        $this->actingAs($owner)->delete(route('settings.branches.destroy', $deleted))->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('settings.branches.store'), branchInput(['code' => 'SDA']))->assertSessionHasErrors('code');

        // Another shop may use the same code.
        $other = provisionTenant('Bengkel Lain');
        $this->actingAs($other->owner)->post(route('settings.branches.store'), branchInput(['code' => 'SDA']))->assertSessionHasNoErrors();
    });

    it('gives a creator limited to some branches access to the new branch', function () {
        $shop = provisionTenant();
        $manager = userWithRole($shop, RoleName::BranchManager);
        inTenant($shop->tenant, null, fn () => $manager->givePermissionTo('branch.create'));

        $this->actingAs($manager)->post(route('settings.branches.store'), branchInput())->assertSessionHasNoErrors();

        $branch = inTenant($shop->tenant, null, fn () => Branch::query()->where('code', 'SDA')->sole());
        expect(inTenant($shop->tenant, null, fn () => $manager->canAccessBranch($branch)))->toBeTrue();
    });
});

describe('editing a branch', function () {
    it('lets a branch manager edit their own branches only', function () {
        $shop = provisionTenant();
        $other = addBranch($shop, 'Cabang Timur');
        $manager = userWithRole($shop, RoleName::BranchManager);

        $this->actingAs($manager)->put(route('settings.branches.update', $shop->branch), branchInput(['name' => 'Pusat Surabaya', 'code' => $shop->branch->code]))
            ->assertSessionHasNoErrors();
        expect($shop->branch->fresh()?->name)->toBe('Pusat Surabaya');

        $this->actingAs($manager)->get(route('settings.branches.edit', $other))->assertForbidden();
        $this->actingAs($manager)->put(route('settings.branches.update', $other), branchInput(['code' => $other->code]))->assertForbidden();
        $this->actingAs($manager)->get(route('settings.branches.create'))->assertForbidden();
        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('settings.branches.edit', $shop->branch))->assertForbidden();
    });

    it('keeps the code once the branch has issued a document', function () {
        $shop = provisionTenant();
        $owner = $shop->owner;
        $code = $shop->branch->code;

        $this->actingAs($owner)->put(route('settings.branches.update', $shop->branch), branchInput(['code' => 'NEW1']))->assertSessionHasNoErrors();
        expect($shop->branch->fresh()?->code)->toBe('NEW1');

        workOrderIn($shop);

        $this->actingAs($owner)->get(route('settings.branches.edit', $shop->branch))->assertInertia(fn (Assert $page) => $page->where('codeLocked', true));
        $this->actingAs($owner)->get(route('settings.branches.edit', secondBranch($shop)))->assertInertia(fn (Assert $page) => $page->where('codeLocked', false));
        $this->actingAs($owner)->put(route('settings.branches.update', $shop->branch), branchInput(['code' => $code]))->assertSessionHasErrors('business');
        $this->actingAs($owner)->put(route('settings.branches.update', $shop->branch), branchInput(['code' => 'NEW1', 'name' => 'Pusat']))->assertSessionHasNoErrors();
        expect($shop->branch->fresh()?->code)->toBe('NEW1');
    });

    it('serves a branch logo only to users who may edit that branch', function () {
        $shop = provisionTenant();
        $other = secondBranch($shop);
        $this->actingAs($shop->owner)->put(route('settings.branches.update', $other), branchInput(['logo' => UploadedFile::fake()->image('logo.png')]))->assertSessionHasNoErrors();

        $this->actingAs($shop->owner)->get(route('settings.branches.logo', $other))->assertOk();
        $this->actingAs(userWithRole($shop, RoleName::BranchManager))->get(route('settings.branches.logo', $other))->assertForbidden();
    });

    it('does not let one shop reach another shop\'s branch', function () {
        $shop = provisionTenant();
        $other = provisionTenant('Bengkel Lain');

        $this->actingAs($shop->owner)->get(route('settings.branches.edit', $other->branch))->assertNotFound();
        $this->actingAs($shop->owner)->delete(route('settings.branches.destroy', $other->branch))->assertNotFound();
        $this->actingAs($shop->owner)->post(route('settings.branches.restore', $other->branch))->assertNotFound();
    });
});

describe('deleting a branch', function () {
    it('removes a branch that was never used for good', function () {
        $shop = provisionTenant();
        $branch = secondBranch($shop);
        // Documents and data of other branches don't make this branch "used".
        workOrderIn($shop);
        mechanicIn($shop);

        $this->actingAs($shop->owner)->delete(route('settings.branches.destroy', $branch))->assertSessionHasNoErrors();

        expect(inTenant($shop->tenant, null, fn () => Branch::withTrashed()->find($branch->id)))->toBeNull()
            ->and(CashAccount::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->exists())->toBeFalse();
    });

    it('keeps a used branch and its data, hidden until it is restored', function () {
        $shop = provisionTenant();
        secondBranch($shop);
        $mechanic = mechanicIn($shop);
        $manager = userWithRole($shop, RoleName::BranchManager);

        $this->actingAs($shop->owner)->delete(route('settings.branches.destroy', $shop->branch))->assertSessionHasNoErrors();

        $branch = inTenant($shop->tenant, null, fn () => Branch::withTrashed()->findOrFail($shop->branch->id));
        expect($branch->trashed())->toBeTrue()
            ->and(Mechanic::query()->withoutGlobalScopes()->whereKey($mechanic->id)->exists())->toBeTrue()
            ->and(inTenant($shop->tenant, null, fn () => $manager->accessibleBranches()->count()))->toBe(0);

        // The owner was working in it: the next request moves them to the remaining branch.
        $this->actingAs($shop->owner)->get(route('settings.shop.edit'))
            ->assertInertia(fn (Assert $page) => $page->has('branchList', 1)->where('branchList.0.code', 'SDA')->where('deletedBranches.0.id', $branch->id));

        // Only someone with access to all branches may restore; then everything is back.
        $this->actingAs($manager)->post(route('settings.branches.restore', $branch))->assertForbidden();
        $this->actingAs($shop->owner)->post(route('settings.branches.restore', $branch))->assertSessionHasNoErrors();
        expect($branch->fresh()?->trashed())->toBeFalse()
            ->and(inTenant($shop->tenant, null, fn () => $manager->accessibleBranches()->count()))->toBe(1);
    });

    it('still shows where past work was done after the branch is deleted', function () {
        $shop = provisionTenant();
        secondBranch($shop);
        $workOrder = workOrderIn($shop);
        workOrders($shop, fn ($service) => $service->cancel($workOrder, 'Batal'));

        $this->actingAs($shop->owner)->delete(route('settings.branches.destroy', $shop->branch))->assertSessionHasNoErrors();

        $name = inTenant($shop->tenant, null, fn () => WorkOrder::query()->withoutGlobalScopes()->with('branch')->findOrFail($workOrder->id)->branch->name);
        expect($name)->toBe($shop->branch->name);
    });

    it('refuses while work, debts or money are left in the branch', function () {
        $shop = provisionTenant();
        secondBranch($shop);
        $owner = $shop->owner;
        $delete = fn () => $this->actingAs($owner)->delete(route('settings.branches.destroy', $shop->branch));

        $workOrder = completedWorkOrder($shop);
        $delete()->assertSessionHasErrors(['business' => __('branches.delete_open_work_orders')]);

        $invoice = invoices($shop, fn (InvoiceService $service) => $service->createFromWorkOrder($workOrder, invoiceInput(), pricesOf($shop, $workOrder)));
        $delete()->assertSessionHasErrors(['business' => __('branches.delete_customer_debts')]);

        invoices($shop, fn (InvoiceService $service) => $service->void($invoice, 'Salah input'));
        $workOrder = $workOrder->fresh();
        workOrders($shop, fn ($service) => $service->cancel($workOrder, 'Batal'));

        $drawer = inTenant($shop->tenant, $shop->branch, fn () => CashAccount::query()->where('type', 'cash_drawer')->sole());
        inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->countTo($drawer, 50_000, 'Saldo awal'));
        $delete()->assertSessionHasErrors(['business' => __('branches.delete_cash_balance')]);

        inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->countTo($drawer, 0, 'Setor ke pemilik'));
        $delete()->assertSessionHasNoErrors();
        expect($shop->branch->fresh()?->trashed())->toBeTrue();
    });

    it('never deletes the last branch, and only the owner deletes', function () {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)->delete(route('settings.branches.destroy', $shop->branch))->assertSessionHasErrors(['business' => __('branches.delete_last')]);

        addBranch($shop);
        $this->actingAs(userWithRole($shop, RoleName::BranchManager))->delete(route('settings.branches.destroy', $shop->branch))->assertForbidden();
        expect($shop->branch->fresh()?->trashed())->toBeFalse();
    });
});
