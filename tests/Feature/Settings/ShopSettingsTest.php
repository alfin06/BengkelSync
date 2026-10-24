<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceRevision;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Invoicing\InvoiceService;
use App\Services\Payments\PaymentService;
use App\Services\Tenancy\ProvisionedTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(config('filesystems.private_disk'));
});

/**
 * A walk-in invoice of Rp 100.000 dated $daysAgo days ago, with Rp 10.000 paid in cash.
 */
function datedInvoice(ProvisionedTenant $shop, int $daysAgo): Invoice
{
    $date = now('Asia/Jakarta')->subDays($daysAgo)->toDateString();
    $invoice = invoices($shop, fn (InvoiceService $service) => $service->createWalkIn(null, invoiceInput(['issue_date' => $date]), [
        ['type' => 'other', 'description' => 'Jasa', 'qty' => 100, 'unit_price' => 100_000],
    ]));

    payments($shop, fn (PaymentService $service) => $service->checkout(
        $invoice,
        $date,
        [['method' => PaymentMethod::query()->with('account')->where('type', 'cash')->firstOrFail(), 'amount' => 10_000]],
        now('Asia/Jakarta')->addWeek()->toDateString(),
    ));

    return $invoice->fresh() ?? $invoice;
}

function lockAfter(ProvisionedTenant $shop, ?int $days): void
{
    test()->actingAs($shop->owner)->put(route('settings.shop.update'), ['name' => $shop->tenant->name, 'invoice_lock_days' => $days])->assertSessionHasNoErrors();
}

describe('shop and branch details', function () {
    it('lets the owner rename the shop and set the lock period, with an audit entry', function () {
        $shop = provisionTenant();

        $this->actingAs($shop->owner)->put(route('settings.shop.update'), ['name' => 'Bengkel Kalimas Lumas', 'invoice_lock_days' => 30])->assertSessionHasNoErrors();

        $tenant = $shop->tenant->fresh();
        expect($tenant?->name)->toBe('Bengkel Kalimas Lumas')
            ->and($tenant?->invoiceLockDays())->toBe(30)
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'shop_settings_changed')->value('new_values')))->toBe(['name' => 'Bengkel Kalimas Lumas', 'invoice_lock_days' => 30]);
    });

    it('keeps shop settings to the owner, branch details to branch managers', function () {
        $shop = provisionTenant();
        $manager = userWithRole($shop, RoleName::BranchManager);

        $this->actingAs($manager)->put(route('settings.shop.update'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($manager)->put(route('settings.branches.update', $shop->branch), ['name' => 'Pusat Surabaya', 'code' => $shop->branch->code, 'timezone' => 'Asia/Jakarta', 'address' => 'Jl. Kalimas 1', 'phone' => '031 1234567'])->assertSessionHasNoErrors();
        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('settings.shop.edit'))->assertForbidden();

        $this->actingAs($manager)->get(route('settings.shop.edit'))
            ->assertInertia(fn (Assert $page) => $page->component('Settings/Shop')->where('shop', null)->where('branchList.0.address', 'Jl. Kalimas 1')->where('can.createBranch', false));

        expect($shop->branch->fresh()?->phone)->toBe('+62311234567');
    });

    it('stores the branch logo privately, serves it to staff, and replaces it cleanly', function () {
        $shop = provisionTenant();
        $disk = Storage::disk(config('filesystems.private_disk'));

        $this->actingAs($shop->owner)->put(route('settings.branches.update', $shop->branch), ['name' => 'Pusat', 'code' => $shop->branch->code, 'timezone' => 'Asia/Jakarta', 'logo' => UploadedFile::fake()->image('logo.png', 200, 80)])->assertSessionHasNoErrors();
        $first = (string) $shop->branch->fresh()?->logo_path;
        expect($disk->exists($first))->toBeTrue();

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('branch.logo'))->assertOk();

        $this->actingAs($shop->owner)->put(route('settings.branches.update', $shop->branch), ['name' => 'Pusat', 'code' => $shop->branch->code, 'timezone' => 'Asia/Jakarta', 'logo' => UploadedFile::fake()->image('baru.png')])->assertSessionHasNoErrors();
        expect($disk->exists($first))->toBeFalse();

        $this->actingAs($shop->owner)->put(route('settings.branches.update', $shop->branch), ['name' => 'Pusat', 'code' => $shop->branch->code, 'timezone' => 'Asia/Jakarta', 'remove_logo' => true])->assertSessionHasNoErrors();
        expect($shop->branch->fresh()?->logo_path)->toBeNull();
        $this->actingAs($shop->owner)->get(route('branch.logo'))->assertNotFound();
        $this->post(route('logout'));
        $this->get(route('branch.logo'))->assertRedirect(route('login'));
    });
});

describe('invoice lock period', function () {
    it('blocks edits, cancellations and payment corrections on old invoices for everyone but the owner', function () {
        $shop = provisionTenant();
        $old = datedInvoice($shop, 40);
        $recent = datedInvoice($shop, 5);
        lockAfter($shop, 30);
        $finance = userWithRole($shop, RoleName::Finance);
        $payment = inTenant($shop->tenant, $shop->branch, fn () => Payment::query()->where('invoice_id', $old->id)->sole());

        $this->actingAs($finance)->get(route('invoices.edit', $old))->assertForbidden();
        $this->actingAs($finance)->post(route('payments.void', $payment), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($finance)->get(route('invoices.show', $old))->assertInertia(fn (Assert $page) => $page->where('locked', true)->where('can.update', false)->where('can.voidPayment', false));
        $this->actingAs($finance)->get(route('invoices.edit', $recent))->assertOk();

        // New payments on an old invoice still work: customers pay late.
        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->post(route('invoices.payments.store', $old), [
            'paid_on' => now('Asia/Jakarta')->toDateString(),
            'lines' => [['payment_method_id' => inTenant($shop->tenant, $shop->branch, fn () => PaymentMethod::query()->where('type', 'cash')->value('id')), 'amount' => 90_000]],
        ])->assertSessionHasNoErrors();
    });

    it('lets the owner still fix an old invoice, marked in the history and the audit log', function () {
        $shop = provisionTenant();
        $old = datedInvoice($shop, 40);
        lockAfter($shop, 30);

        $this->actingAs($shop->owner)->put(route('invoices.update', $old), [
            'reason' => 'Salah harga, ditemukan saat tutup buku',
            'due_date' => now('Asia/Jakarta')->addWeek()->toDateString(),
            'lines' => [['type' => 'other', 'description' => 'Jasa', 'qty' => '1', 'unit_price' => 90_000]],
        ])->assertSessionHasNoErrors();

        expect($old->fresh()?->total)->toBe(90_000)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => InvoiceRevision::query()->sole()->snapshot['edited_after_lock']))->toBeTrue()
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'invoice_revised')->value('new_values')['after_lock'] ?? false))->toBeTrue();

        $this->actingAs($shop->owner)->get(route('invoices.revisions', $old))
            ->assertInertia(fn (Assert $page) => $page->where('history.0.after_lock', true));
    });

    it('locks nothing when the period is empty', function () {
        $shop = provisionTenant();
        $old = datedInvoice($shop, 400);
        lockAfter($shop, null);

        $this->actingAs(userWithRole($shop, RoleName::Finance))->get(route('invoices.edit', $old))->assertOk();
    });
});
