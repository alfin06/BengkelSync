<?php

namespace App\Services\Settings;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Services\Audit\AuditLogger;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shop and branch settings: the shop's name and invoice lock period, and the active
 * branch's details shown on printed documents (name, address, phone, NPWP, logo).
 * Branch changes are audited by the Branch model; shop changes are recorded here.
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

    /**
     * @param  array{name: string, address?: string|null, phone?: string|null, npwp?: string|null}  $data
     */
    public function updateBranch(array $data, ?UploadedFile $logo = null, bool $removeLogo = false): Branch
    {
        $branch = $this->context->requireBranch();
        $oldPath = $branch->logo_path;
        $newPath = $logo === null ? null : $this->storeLogo($logo);

        try {
            DB::transaction(function () use ($branch, $data, $oldPath, $newPath, $removeLogo): void {
                $branch->fill([
                    'name' => trim($data['name']),
                    'address' => $this->nullIfBlank($data['address'] ?? null),
                    'phone' => Phone::normalize($data['phone'] ?? null),
                    'npwp' => $this->nullIfBlank($data['npwp'] ?? null),
                ]);

                if ($newPath !== null || $removeLogo) {
                    $branch->logo_path = $newPath;
                }

                $branch->save();

                if ($oldPath !== null && $oldPath !== $branch->logo_path) {
                    DB::afterCommit(fn () => $this->disk()->delete($oldPath));
                }
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->disk()->delete($newPath);
            }

            throw $exception;
        }

        return $branch;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.private_disk'));
    }

    private function storeLogo(UploadedFile $file): string
    {
        $path = $this->disk()->putFileAs(
            'tenants/'.$this->context->requireTenant()->getKey().'/branding',
            $file,
            Str::uuid()->toString().'.'.$file->guessExtension(),
        );

        if ($path === false) {
            throw new BusinessRuleViolation(__('shop_settings.logo_upload_failed'));
        }

        return $path;
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
