<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\InvoiceSettingsRequest;
use App\Services\Branches\BranchSettingsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branch settings: tax on invoices and the invoice footer (decided by the branch manager).
 */
class InvoiceSettingsController extends Controller
{
    public function edit(Request $request, TenantContext $context): Response
    {
        abort_unless($request->user()?->can(Permission::BranchUpdate->value), 403);

        $branch = $context->requireBranch();

        return Inertia::render('Settings/Invoice', [
            'settings' => [
                'tax_enabled' => $branch->tax_enabled,
                'tax_name' => $branch->tax_name,
                'tax_rate' => rtrim(rtrim($branch->tax_rate, '0'), '.'),
                'tax_inclusive' => $branch->tax_inclusive,
                'invoice_footer' => $branch->invoice_footer ?? '',
            ],
        ]);
    }

    public function update(InvoiceSettingsRequest $request, BranchSettingsService $settings): RedirectResponse
    {
        $settings->updateInvoiceSettings([
            'tax_enabled' => $request->boolean('tax_enabled'),
            'tax_name' => $request->string('tax_name')->toString(),
            'tax_rate' => $request->taxRate(),
            'tax_inclusive' => $request->boolean('tax_inclusive'),
            'invoice_footer' => $request->input('invoice_footer'),
        ]);

        return back()->with('success', __('invoices.settings.saved'));
    }
}
