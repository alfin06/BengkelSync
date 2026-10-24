<?php

namespace App\Services\Settings;

use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Shop settings: the shop's name and invoice lock period. Branch details live in
 * BranchService.
 */
class ShopSettingsService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function updateShop(string $name, ?int $invoiceLockDays): void
    {
        $tenant = $this->context->requireTenant();
        $old = ['name' => $tenant->name, 'invoice_lock_days' => $tenant->invoiceLockDays()];
        $new = ['name' => trim($name), 'invoice_lock_days' => $invoiceLockDays];

        if ($old === $new) {
            return;
        }

        DB::transaction(function () use ($tenant, $old, $new): void {
            $tenant->forceFill([
                'name' => $new['name'],
                'settings' => [...($tenant->settings ?? []), 'invoice_lock_days' => $new['invoice_lock_days']],
            ])->save();

            $this->audit->record('shop_settings_changed', null, $old, $new);
        });
    }
}
