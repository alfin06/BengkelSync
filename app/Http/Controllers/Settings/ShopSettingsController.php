<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BranchProfileRequest;
use App\Http\Requests\Settings\ShopSettingsRequest;
use App\Models\User;
use App\Services\Settings\ShopSettingsService;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Toko & cabang": the shop (Owner) and the active branch (branch manager).
 */
class ShopSettingsController extends Controller
{
    public function __construct(private readonly ShopSettingsService $settings) {}

    public function edit(Request $request, TenantContext $context): Response
    {
        /** @var User $user */
        $user = $request->user();
        $canShop = $user->can(Permission::SettingsView->value);
        $canBranch = $user->can(Permission::BranchUpdate->value);
        abort_unless($canShop || $canBranch, 403);

        $tenant = $context->requireTenant();
        $branch = $context->requireBranch();

        return Inertia::render('Settings/Shop', [
            'shop' => $canShop ? ['name' => $tenant->name, 'invoice_lock_days' => $tenant->invoiceLockDays()] : null,
            'branch' => $canBranch ? [
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address ?? '',
                'phone' => $branch->phone === null ? '' : Phone::format($branch->phone),
                'npwp' => $branch->npwp ?? '',
                'logo_url' => $branch->logoUrl(),
            ] : null,
            'can' => [
                'updateShop' => $user->can(Permission::SettingsUpdate->value),
                'updateBranch' => $canBranch,
                'taxSettings' => $canBranch,
            ],
        ]);
    }

    public function updateShop(ShopSettingsRequest $request): RedirectResponse
    {
        $this->settings->updateShop($request->string('name')->toString(), $request->filled('invoice_lock_days') ? $request->integer('invoice_lock_days') : null);

        return back()->with('success', __('shop_settings.shop_saved'));
    }

    public function updateBranch(BranchProfileRequest $request): RedirectResponse
    {
        $this->settings->updateBranch(
            $request->only(['name', 'address', 'phone', 'npwp']),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return back()->with('success', __('shop_settings.branch_saved'));
    }

    /**
     * The active branch's logo for printed documents: only for logged-in staff of the
     * branch, from the private disk.
     */
    public function logo(TenantContext $context): StreamedResponse
    {
        $path = $context->requireBranch()->logo_path;
        abort_if($path === null || ! $this->settings->disk()->exists($path), 404);

        return $this->settings->disk()->response($path, 'logo', [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
