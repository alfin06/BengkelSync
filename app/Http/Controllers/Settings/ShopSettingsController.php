<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ShopSettingsRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Settings\ShopSettingsService;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Toko & cabang": the shop (Owner) and the list of branches (add and delete: Owner;
 * edit: branch managers for their own branches).
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
        $activeId = $context->requireBranch()->getKey();
        $row = fn (Branch $branch): array => [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'address' => $branch->address,
            'phone' => $branch->phone === null ? null : Phone::format($branch->phone),
            'is_active' => $branch->id === $activeId,
        ];

        return Inertia::render('Settings/Shop', [
            'shop' => $canShop ? ['name' => $tenant->name, 'invoice_lock_days' => $tenant->invoiceLockDays()] : null,
            'branchList' => $canBranch ? $user->accessibleBranches()->orderBy('id')->get()->map($row)->all() : [],
            'deletedBranches' => $user->can('restore', new Branch) ? Branch::onlyTrashed()->orderBy('name')->get()->map($row)->all() : [],
            'can' => [
                'updateShop' => $user->can(Permission::SettingsUpdate->value),
                'updateBranch' => $canBranch,
                'createBranch' => $user->can('create', Branch::class),
                'deleteBranch' => $user->can(Permission::BranchDelete->value),
                'taxSettings' => $canBranch,
            ],
        ]);
    }

    public function updateShop(ShopSettingsRequest $request): RedirectResponse
    {
        $this->settings->updateShop($request->string('name')->toString(), $request->filled('invoice_lock_days') ? $request->integer('invoice_lock_days') : null);

        return back()->with('success', __('shop_settings.shop_saved'));
    }
}
