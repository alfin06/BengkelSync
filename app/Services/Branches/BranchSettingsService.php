<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Settings of the active branch. Branch is Auditable, so every change is logged.
 */
class BranchSettingsService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Whether new invoices carry tax, its name, rate and mode, and the footer text.
     * Existing invoices keep the settings they were made with (SPEC section 5).
     *
     * @param  array{tax_enabled: bool, tax_name: string, tax_rate: string, tax_inclusive: bool, invoice_footer: string|null}  $settings
     */
    public function updateInvoiceSettings(array $settings): Branch
    {
        $branch = $this->context->requireBranch();
        $footer = $settings['invoice_footer'] === null ? '' : trim($settings['invoice_footer']);

        DB::transaction(fn () => $branch->update([
            'tax_enabled' => $settings['tax_enabled'],
            'tax_name' => trim($settings['tax_name']),
            'tax_rate' => $settings['tax_rate'],
            'tax_inclusive' => $settings['tax_inclusive'],
            'invoice_footer' => $footer === '' ? null : $footer,
        ]));

        return $branch;
    }
}
