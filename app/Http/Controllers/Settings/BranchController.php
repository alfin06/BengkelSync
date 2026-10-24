<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BranchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Settings\BranchService;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adding, editing, deleting and restoring branches. The list is on "Toko & cabang".
 */
class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branches) {}

    public function create(): Response
    {
        Gate::authorize('create', Branch::class);

        return Inertia::render('Settings/BranchForm', [
            'branchItem' => null,
            'timezones' => BranchService::TIMEZONES,
            'codeLocked' => false,
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branch = $this->branches->create($user, $request->branchData(), $request->file('logo'));

        return redirect()->route('settings.shop.edit')->with('success', __('branches.created', ['name' => $branch->name]));
    }

    public function edit(Branch $branch): Response
    {
        Gate::authorize('update', $branch);

        return Inertia::render('Settings/BranchForm', [
            'branchItem' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'timezone' => $branch->timezone,
                'address' => $branch->address ?? '',
                'phone' => $branch->phone === null ? '' : Phone::format($branch->phone),
                'npwp' => $branch->npwp ?? '',
                'logo_url' => $branch->logo_path === null ? null : route('settings.branches.logo', ['branch' => $branch, 'v' => substr(md5($branch->logo_path), 0, 8)]),
            ],
            'timezones' => BranchService::TIMEZONES,
            'codeLocked' => $this->branches->hasDocuments($branch),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->branches->update($branch, $request->branchData(), $request->file('logo'), $request->boolean('remove_logo'));

        return redirect()->route('settings.shop.edit')->with('success', __('shop_settings.branch_saved'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        Gate::authorize('delete', $branch);

        $removed = $this->branches->delete($branch);

        return redirect()->route('settings.shop.edit')
            ->with('success', __($removed ? 'branches.removed' : 'branches.archived', ['name' => $branch->name]));
    }

    public function restore(Branch $branch): RedirectResponse
    {
        Gate::authorize('restore', $branch);

        $this->branches->restore($branch);

        return redirect()->route('settings.shop.edit')->with('success', __('branches.restored', ['name' => $branch->name]));
    }

    /**
     * A branch's logo while editing it (the user must be allowed to edit that branch).
     */
    public function logo(Branch $branch): StreamedResponse
    {
        Gate::authorize('update', $branch);

        return $this->logoResponse($branch->logo_path);
    }

    /**
     * The active branch's logo for printed documents: only for logged-in staff of the
     * branch, from the private disk.
     */
    public function activeLogo(TenantContext $context): StreamedResponse
    {
        return $this->logoResponse($context->requireBranch()->logo_path);
    }

    private function logoResponse(?string $path): StreamedResponse
    {
        abort_if($path === null || ! $this->branches->disk()->exists($path), 404);

        return $this->branches->disk()->response($path, 'logo', [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
