<?php

namespace App\Services\Mechanics;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Mechanic;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mechanics of the active branch. Identity data (NIK, KTP photo) is only written when the
 * caller passes it; FormRequests make sure only users with mechanic.view_identity can.
 */
class MechanicService
{
    public const STATUSES = ['active', 'inactive', 'all'];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return Builder<Mechanic>
     */
    public function search(?string $term, string $status = 'active'): Builder
    {
        $term = trim((string) $term);

        return Mechanic::query()
            ->when($status !== 'all', fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(array $data, ?UploadedFile $ktp = null): Mechanic
    {
        $mechanic = new Mechanic($this->prepare($data));

        return $this->saveWithKtp($mechanic, $ktp, removeKtp: false);
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(Mechanic $mechanic, array $data, ?UploadedFile $ktp = null, bool $removeKtp = false): Mechanic
    {
        $mechanic->fill($this->prepare($data));

        return $this->saveWithKtp($mechanic, $ktp, $removeKtp);
    }

    /**
     * Mechanics who worked on a job cannot be deleted, only deactivated (same rule as the
     * service catalog), so work order history keeps their name.
     */
    public function delete(Mechanic $mechanic): void
    {
        if ($this->isUsed($mechanic)) {
            throw new BusinessRuleViolation(__('mechanics.delete_used'));
        }

        DB::transaction(fn () => $mechanic->delete());
    }

    /**
     * Work orders (SPEC section 4, work_order_mechanic) will reference mechanics. That table
     * does not exist yet, so no mechanic is used.
     */
    public function isUsed(Mechanic $mechanic): bool
    {
        return false;
    }

    /**
     * The new photo is written before the transaction and removed again if it fails; the
     * old photo is only deleted after the transaction committed, so a failed save never
     * loses a stored KTP.
     */
    private function saveWithKtp(Mechanic $mechanic, ?UploadedFile $ktp, bool $removeKtp): Mechanic
    {
        $oldPath = $mechanic->ktp_path;
        $newPath = $ktp === null ? null : $this->storeKtp($ktp);

        if ($newPath !== null || $removeKtp) {
            $mechanic->ktp_path = $newPath;
        }

        try {
            DB::transaction(function () use ($mechanic, $oldPath): void {
                $mechanic->save();

                if ($oldPath !== null && $oldPath !== $mechanic->ktp_path) {
                    DB::afterCommit(fn () => $this->disk()->delete($oldPath));
                }
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->disk()->delete($newPath);
            }

            throw $exception;
        }

        return $mechanic;
    }

    private function storeKtp(UploadedFile $file): string
    {
        // Random file name: nothing about the person in the path.
        $path = $this->disk()->putFileAs(
            'tenants/'.$this->context->requireTenant()->getKey().'/mechanics',
            $file,
            Str::uuid()->toString().'.'.$file->guessExtension(),
        );

        if ($path === false) {
            throw new BusinessRuleViolation(__('mechanics.ktp_upload_failed'));
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $prepared = [
            'name' => trim((string) $data['name']),
            'phone' => Phone::normalize(isset($data['phone']) ? (string) $data['phone'] : null),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ];

        // Only touch the NIK when the (authorized) caller sent it.
        if (array_key_exists('nik', $data)) {
            $nik = preg_replace('/\D/', '', (string) $data['nik']) ?? '';
            $prepared['nik'] = $nik === '' ? null : $nik;
        }

        return $prepared;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.private_disk'));
    }
}
