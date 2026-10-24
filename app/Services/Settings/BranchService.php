<?php

namespace App\Services\Settings;

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseStatus;
use App\Enums\WorkOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Mechanic;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Cash\CashService;
use App\Services\Cash\CashSetupService;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Branches of the shop: add, edit (details printed on documents, code, time zone, logo),
 * delete and restore. Branch changes are audited by the Branch model.
 *
 * Deleting never loses records: a branch with any data is soft-deleted (hidden, can be
 * restored) once its open work, debts and cash are settled; only a branch without any
 * data is removed for good.
 */
class BranchService
{
    /** Time zones of Indonesia: WIB, WITA, WIT. */
    public const TIMEZONES = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];

    /**
     * Every model whose rows make a branch "used". Mechanics count too: they are records
     * of the branch, and the database refuses to delete a branch that still has them.
     *
     * @var list<class-string<Model>>
     */
    private const DATA_MODELS = [
        WorkOrder::class,
        Invoice::class,
        PaymentReceipt::class,
        Payment::class,
        Purchase::class,
        SupplierPayment::class,
        Expense::class,
        StockMovement::class,
        CashMovement::class,
        Mechanic::class,
        DocumentSequence::class,
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly CashSetupService $cashSetup,
        private readonly CashService $cash,
    ) {}

    /**
     * A new branch with the same defaults as the shop's first one (cash drawer, petty
     * cash, cash payment method, Front Desk cash limit). Users with access to all branches
     * see it at once; a creator limited to some branches is given access to it.
     *
     * @param  array{name: string, code: string, timezone: string, address?: string|null, phone?: string|null, npwp?: string|null}  $data
     */
    public function create(User $actor, array $data, ?UploadedFile $logo = null): Branch
    {
        $logoPath = $logo === null ? null : $this->storeLogo($logo);

        try {
            return DB::transaction(function () use ($actor, $data, $logoPath): Branch {
                $branch = Branch::query()->create([
                    ...$this->details($data),
                    'code' => Str::upper(trim($data['code'])),
                    'timezone' => $data['timezone'],
                    'logo_path' => $logoPath,
                ]);

                $this->cashSetup->provisionDefaults($branch);

                if (! $actor->all_branches) {
                    $branch->users()->attach($actor);
                }

                return $branch;
            });
        } catch (Throwable $exception) {
            if ($logoPath !== null) {
                $this->disk()->delete($logoPath);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{name: string, code: string, timezone: string, address?: string|null, phone?: string|null, npwp?: string|null}  $data
     */
    public function update(Branch $branch, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): Branch
    {
        $code = Str::upper(trim($data['code']));

        if ($code !== $branch->code && $this->hasDocuments($branch)) {
            throw new BusinessRuleViolation(__('branches.code_locked'));
        }

        $oldPath = $branch->logo_path;
        $newPath = $logo === null ? null : $this->storeLogo($logo);

        try {
            DB::transaction(function () use ($branch, $data, $code, $oldPath, $newPath, $removeLogo): void {
                $branch->fill([...$this->details($data), 'code' => $code, 'timezone' => $data['timezone']]);

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

    /**
     * @return bool true when the branch was removed for good (it had no data), false when
     *              it was soft-deleted and can be restored
     */
    public function delete(Branch $branch): bool
    {
        if (Branch::query()->count() <= 1) {
            throw new BusinessRuleViolation(__('branches.delete_last'));
        }

        $this->ensureSettled($branch);

        if ($this->hasData($branch)) {
            $branch->delete();

            return false;
        }

        $logoPath = $branch->logo_path;

        // Cash accounts, stock thresholds and staff access go with it (foreign keys
        // cascade); payment methods are removed first because they point at the cash
        // accounts. There are no movements or documents to keep. The audit entry is
        // written without a branch, since the branch row no longer exists.
        DB::transaction(function () use ($branch, $logoPath): void {
            $this->inBranch($branch, fn () => PaymentMethod::query()->delete());
            $this->context->run($this->context->requireTenant(), null, fn () => $branch->forceDelete());

            if ($logoPath !== null) {
                DB::afterCommit(fn () => $this->disk()->delete($logoPath));
            }
        });

        return true;
    }

    public function restore(Branch $branch): void
    {
        $branch->restore();
    }

    /**
     * Whether the branch has issued a numbered document: from then on its code is part of
     * those numbers and stays fixed.
     */
    public function hasDocuments(Branch $branch): bool
    {
        return $this->inBranch($branch, fn (): bool => $this->rowsOf(DocumentSequence::class, $branch)->exists());
    }

    public function hasData(Branch $branch): bool
    {
        return $this->inBranch($branch, function () use ($branch): bool {
            foreach (self::DATA_MODELS as $model) {
                if ($this->rowsOf($model, $branch)->exists()) {
                    return true;
                }
            }

            return false;
        });
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.private_disk'));
    }

    /**
     * Nothing may be left hanging in a branch that disappears from the app: open work
     * orders, unpaid customer invoices, unpaid supplier bills or money in its accounts.
     */
    private function ensureSettled(Branch $branch): void
    {
        $this->inBranch($branch, function (): void {
            $open = [WorkOrderStatus::Draft, WorkOrderStatus::Queued, WorkOrderStatus::InProgress, WorkOrderStatus::WaitingParts, WorkOrderStatus::Completed];

            if (WorkOrder::query()->whereIn('status', $open)->exists()) {
                throw new BusinessRuleViolation(__('branches.delete_open_work_orders'));
            }

            if (Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])->exists()) {
                throw new BusinessRuleViolation(__('branches.delete_customer_debts'));
            }

            if (Purchase::query()->whereIn('status', [PurchaseStatus::Unpaid, PurchaseStatus::Partial])->exists()) {
                throw new BusinessRuleViolation(__('branches.delete_supplier_debts'));
            }

            $balances = $this->cash->balances(CashAccount::query()->get());

            if (collect($balances)->contains(fn (int $balance): bool => $balance !== 0)) {
                throw new BusinessRuleViolation(__('branches.delete_cash_balance'));
            }
        });
    }

    /**
     * All rows of a model that belong to the branch, soft-deleted ones included (the
     * database still sees them). Filtered on branch_id explicitly: not every model with
     * a branch_id is branch-scoped (document sequences are only tenant-scoped).
     *
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    private function rowsOf(string $model, Branch $branch): Builder
    {
        return $model::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where((new $model)->qualifyColumn('branch_id'), $branch->getKey());
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inBranch(Branch $branch, callable $callback): mixed
    {
        return $this->context->run($this->context->requireTenant(), $branch, $callback);
    }

    /**
     * @param  array{name: string, address?: string|null, phone?: string|null, npwp?: string|null}  $data
     * @return array{name: string, address: string|null, phone: string|null, npwp: string|null}
     */
    private function details(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'address' => $this->nullIfBlank($data['address'] ?? null),
            'phone' => Phone::normalize($data['phone'] ?? null),
            'npwp' => $this->nullIfBlank($data['npwp'] ?? null),
        ];
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
