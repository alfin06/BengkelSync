<?php

namespace App\Services\Cash;

use App\Enums\CashMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Support\Money;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only way money moves between the shop's cash accounts (CLAUDE.md rule 6, cash).
 *
 * Every change locks the account row, appends a cash_movements row with the new running
 * balance, and checks the balance rule: physical cash (drawer, petty cash) can never go
 * below zero; bank and other accounts may (pilot decision 1).
 */
class CashService
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @param  int  $amount  signed Rupiah: positive in, negative out
     */
    public function record(
        CashAccount $account,
        CashMovementType $type,
        int $amount,
        ?Model $reference = null,
        ?string $note = null,
        ?string $transferGroup = null,
    ): CashMovement {
        if ($amount === 0) {
            throw new BusinessRuleViolation(__('cash.zero_amount'));
        }

        return DB::transaction(function () use ($account, $type, $amount, $reference, $note, $transferGroup): CashMovement {
            $locked = $this->lock($account);

            if (! $locked->is_active) {
                throw new BusinessRuleViolation(__('cash.account_inactive', ['account' => $locked->name]));
            }

            $before = $this->balance($locked);
            $after = $before + $amount;

            if ($after < 0 && $locked->type->isPhysical()) {
                throw new BusinessRuleViolation(__('cash.insufficient', [
                    'account' => $locked->name,
                    'balance' => Money::format($before),
                ]));
            }

            return CashMovement::query()->create([
                'cash_account_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $after,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'transfer_group' => $transferGroup,
                'note' => $note === null || trim($note) === '' ? null : trim($note),
                'created_by' => $this->userId(),
            ]);
        });
    }

    /**
     * Move money between two accounts of the branch, e.g. topping up petty cash from the
     * drawer. Both movements are written together or not at all.
     *
     * @return array{0: CashMovement, 1: CashMovement} out, in
     */
    public function transfer(CashAccount $from, CashAccount $to, int $amount, ?string $note = null): array
    {
        if ($from->is($to)) {
            throw new BusinessRuleViolation(__('cash.same_account'));
        }

        if ($amount <= 0) {
            throw new BusinessRuleViolation(__('cash.zero_amount'));
        }

        return DB::transaction(function () use ($from, $to, $amount, $note): array {
            // Lock both rows in a fixed order, so two opposite transfers cannot deadlock.
            $sorted = [];
            foreach ([$from, $to] as $account) {
                $sorted[$account->id] = $account;
            }
            ksort($sorted);
            foreach ($sorted as $account) {
                $this->lock($account);
            }

            $group = Str::uuid()->toString();

            return [
                $this->record($from, CashMovementType::TransferOut, -$amount, note: $note, transferGroup: $group),
                $this->record($to, CashMovementType::TransferIn, $amount, note: $note, transferGroup: $group),
            ];
        });
    }

    /**
     * Cash count: set the balance to what was counted, with a required reason. Also used
     * for the opening balance ("Saldo awal").
     */
    public function countTo(CashAccount $account, int $counted, string $reason): ?CashMovement
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        if ($counted < 0 && $account->type->isPhysical()) {
            throw new BusinessRuleViolation(__('cash.negative_physical'));
        }

        return DB::transaction(function () use ($account, $counted, $reason): ?CashMovement {
            $difference = $counted - $this->balance($this->lock($account));

            return $difference === 0
                ? null
                : $this->record($account, CashMovementType::Adjustment, $difference, note: $reason);
        });
    }

    /**
     * Current balance: the running balance of the latest movement.
     */
    public function balance(CashAccount $account): int
    {
        return (int) (CashMovement::query()
            ->where('cash_account_id', $account->id)
            ->latest('id')
            ->value('balance_after') ?? 0);
    }

    /**
     * Balances of several accounts in one query.
     *
     * @param  Collection<int, CashAccount>  $accounts
     * @return array<int, int> account id => balance
     */
    public function balances(Collection $accounts): array
    {
        $latest = CashMovement::query()
            ->whereIn('id', CashMovement::query()
                ->selectRaw('MAX(id)')
                ->whereIn('cash_account_id', $accounts->modelKeys())
                ->groupBy('cash_account_id'))
            ->pluck('balance_after', 'cash_account_id');

        return $accounts->mapWithKeys(fn (CashAccount $account) => [
            $account->id => (int) ($latest[$account->id] ?? 0),
        ])->all();
    }

    /**
     * Lock the account row until the transaction ends, so two simultaneous movements
     * cannot both read the same running balance.
     */
    private function lock(CashAccount $account): CashAccount
    {
        return CashAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }

    private function userId(): ?int
    {
        $id = $this->auth->guard()->id();

        return is_int($id) ? $id : null;
    }
}
