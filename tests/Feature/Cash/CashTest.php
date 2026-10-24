<?php

use App\Enums\CashMovementType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\PaymentMethod;
use App\Services\Cash\CashService;
use App\Services\Tenancy\ProvisionedTenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function cash(ProvisionedTenant $shop, callable $callback): mixed
{
    return inTenant($shop->tenant, $shop->branch, fn () => $callback(app(CashService::class)));
}

describe('CashService', function () {
    it('keeps a running balance from the movements', function () {
        $shop = provisionTenant();
        $drawer = accountIn($shop);

        cash($shop, function (CashService $cash) use ($drawer) {
            $cash->record($drawer, CashMovementType::CustomerPayment, 250_000);
            $cash->record($drawer, CashMovementType::Expense, -40_000, note: 'Makan siang');

            expect($cash->balance($drawer))->toBe(210_000)
                ->and(CashMovement::query()->orderBy('id')->pluck('balance_after')->all())->toBe([250_000, 210_000]);
        });
    });

    it('never lets physical cash go below zero', function () {
        $shop = provisionTenant();
        $petty = accountIn($shop, 'petty');

        cash($shop, function (CashService $cash) use ($petty) {
            $cash->record($petty, CashMovementType::TransferIn, 50_000);

            expect(fn () => $cash->record($petty, CashMovementType::Expense, -60_000))->toThrow(BusinessRuleViolation::class)
                ->and($cash->balance($petty))->toBe(50_000);
        });
    });

    it('lets a bank account go below zero (decision 1)', function () {
        $shop = provisionTenant();
        $bank = accountIn($shop, 'bank');

        cash($shop, fn (CashService $cash) => $cash->record($bank, CashMovementType::SupplierPayment, -1_000_000));

        expect(cash($shop, fn (CashService $cash) => $cash->balance($bank)))->toBe(-1_000_000);
    });

    it('transfers between accounts atomically', function () {
        $shop = provisionTenant();
        $drawer = accountIn($shop);
        $petty = accountIn($shop, 'petty');

        cash($shop, function (CashService $cash) use ($drawer, $petty) {
            $cash->record($drawer, CashMovementType::CustomerPayment, 500_000);
            [$out, $in] = $cash->transfer($drawer, $petty, 200_000, 'Isi kas kecil');

            expect($cash->balance($drawer))->toBe(300_000)
                ->and($cash->balance($petty))->toBe(200_000)
                ->and($out->transfer_group)->toBe($in->transfer_group)->not->toBeNull();

            // Not enough in the drawer: neither side is written.
            expect(fn () => $cash->transfer($drawer, $petty, 400_000))->toThrow(BusinessRuleViolation::class)
                ->and($cash->balance($petty))->toBe(200_000)
                ->and(CashMovement::query()->count())->toBe(3);
        });
    });

    it('counts cash to the counted amount with a reason', function () {
        $shop = provisionTenant();
        $drawer = accountIn($shop);

        cash($shop, function (CashService $cash) use ($drawer) {
            $cash->countTo($drawer, 1_000_000, 'Saldo awal');
            $movement = $cash->countTo($drawer, 980_000, 'Selisih hitung');

            expect($movement?->amount)->toBe(-20_000)
                ->and($movement?->type)->toBe(CashMovementType::Adjustment)
                ->and($cash->countTo($drawer, 980_000, 'Sama'))->toBeNull()
                ->and(fn () => $cash->countTo($drawer, 1, ''))->toThrow(BusinessRuleViolation::class);
        });
    });

    it('refuses movements on inactive accounts', function () {
        $shop = provisionTenant();
        $old = accountIn($shop, 'drawer', ['is_active' => false]);

        cash($shop, fn (CashService $cash) => $cash->record($old, CashMovementType::CustomerPayment, 1_000));
    })->throws(BusinessRuleViolation::class);

    it('keeps cash movements append-only', function () {
        $shop = provisionTenant();
        $drawer = accountIn($shop);

        cash($shop, function (CashService $cash) use ($drawer) {
            $movement = $cash->record($drawer, CashMovementType::CustomerPayment, 1_000);

            expect(fn () => $movement->forceFill(['amount' => 5])->save())->toThrow(LogicException::class)
                ->and(fn () => $movement->delete())->toThrow(LogicException::class);
        });
    });
});

describe('Kas page', function () {
    it('gives every new shop a cash drawer, petty cash and the "Tunai" method', function () {
        $shop = provisionTenant();

        inTenant($shop->tenant, $shop->branch, function () {
            $method = PaymentMethod::query()->with('account')->sole();

            expect(CashAccount::query()->orderBy('sort')->pluck('type')->map->value->all())->toBe(['cash_drawer', 'petty_cash'])
                ->and($method->name)->toBe('Tunai')
                ->and($method->account->name)->toBe('Laci Kasir');
        });
    });

    it('hides bank balances from users without financial access', function () {
        $shop = provisionTenant();
        $bank = accountIn($shop, 'bank');
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $finance = userWithRole($shop, RoleName::Finance);

        $this->actingAs($frontDesk)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page->has('accounts', 2)->where('hiddenFinancial', true));
        $this->actingAs($frontDesk)->get(route('cash.show', $bank))->assertForbidden();

        $this->actingAs($finance)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page->has('accounts', 3)->where('hiddenFinancial', false));
    });

    it('transfers and counts through the page, with permissions', function () {
        $shop = provisionTenant();
        $finance = userWithRole($shop, RoleName::Finance);
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        [$drawer, $petty] = inTenant($shop->tenant, $shop->branch, fn () => CashAccount::query()->orderBy('sort')->get()->all());

        $this->actingAs($frontDesk)->post(route('cash.count', $drawer), ['counted_amount' => 500_000, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($finance)->post(route('cash.count', $drawer), ['counted_amount' => 500_000, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($finance)->post(route('cash.count', $drawer), ['counted_amount' => 500_000, 'reason' => 'Saldo awal'])->assertSessionHas('success');

        $this->actingAs($frontDesk)
            ->post(route('cash.transfer'), ['from_account_id' => $drawer->id, 'to_account_id' => $petty->id, 'amount' => 100_000])
            ->assertForbidden();
        $this->actingAs($finance)
            ->post(route('cash.transfer'), ['from_account_id' => $drawer->id, 'to_account_id' => $petty->id, 'amount' => 900_000])
            ->assertSessionHas('error')
            // Also in the error bag, so the open dialog stays open (Inertia onError).
            ->assertSessionHasErrors('business');
        $this->actingAs($finance)
            ->post(route('cash.transfer'), ['from_account_id' => $drawer->id, 'to_account_id' => $petty->id, 'amount' => 100_000])
            ->assertSessionHas('success');

        expect(cash($shop, fn (CashService $cash) => [$cash->balance($drawer), $cash->balance($petty)]))->toBe([400_000, 100_000]);
    });

    it('cannot use accounts of another branch', function () {
        $shop = provisionTenant();
        $second = addBranch($shop);
        $drawer = inTenant($shop->tenant, $shop->branch, fn () => CashAccount::query()->orderBy('sort')->firstOrFail());
        $elsewhere = inTenant($shop->tenant, $second, fn () => CashAccount::factory()->create());

        $this->actingAs($shop->owner)->get(route('cash.show', $elsewhere))->assertNotFound();
        $this->actingAs($shop->owner)
            ->post(route('cash.transfer'), ['from_account_id' => $drawer->id, 'to_account_id' => $elsewhere->id, 'amount' => 1])
            ->assertSessionHasErrors('to_account_id');
    });

    it('filters the history by date in the branch timezone', function () {
        $shop = provisionTenant();
        $drawer = accountIn($shop);

        Carbon::setTestNow('2026-10-24 18:00:00'); // 25 Oct 01:00 in Jakarta
        cash($shop, fn (CashService $cash) => $cash->record($drawer, CashMovementType::CustomerPayment, 1_000));
        Carbon::setTestNow();

        $count = fn (string $date) => $this->actingAs($shop->owner)
            ->get(route('cash.show', [$drawer, 'from' => $date, 'to' => $date]))
            ->viewData('page')['props']['movements']['total'];

        expect($count('2026-10-25'))->toBe(1)->and($count('2026-10-24'))->toBe(0);
    });
});

describe('payment settings', function () {
    it('lets managers manage accounts and methods, not finance staff', function () {
        $shop = provisionTenant();
        $manager = userWithRole($shop, RoleName::BranchManager);
        $finance = userWithRole($shop, RoleName::Finance);

        $this->actingAs($finance)->get(route('settings.payments.index'))->assertForbidden();

        $this->actingAs($manager)->post(route('settings.payments.accounts.store'), ['name' => 'BCA Anton', 'type' => 'bank'])->assertSessionHasNoErrors();
        $bca = inTenant($shop->tenant, $shop->branch, fn () => CashAccount::query()->where('name', 'BCA Anton')->sole());

        $this->actingAs($manager)->post(route('settings.payments.methods.store'), [
            'name' => 'Kartu Kredit',
            'type' => 'credit_card',
            'cash_account_id' => $bca->id,
            'surcharge_percent' => '3',
            'needs_reference' => true,
        ])->assertSessionHasNoErrors();

        $method = inTenant($shop->tenant, $shop->branch, fn () => PaymentMethod::query()->where('name', 'Kartu Kredit')->sole());
        expect($method->surcharge_percent)->toBe('3.00')->and($method->needs_reference)->toBeTrue();
    });

    it('validates names, surcharge and the account', function (array $data, string $field) {
        $shop = provisionTenant();
        $drawer = inTenant($shop->tenant, $shop->branch, fn () => CashAccount::query()->orderBy('sort')->firstOrFail());

        $this->actingAs($shop->owner)
            ->post(route('settings.payments.methods.store'), [
                'name' => 'QRIS BCA', 'type' => 'qris', 'cash_account_id' => $drawer->id, 'surcharge_percent' => '0', ...$data,
            ])
            ->assertSessionHasErrors($field);
    })->with([
        'duplicate name' => [['name' => 'Tunai'], 'name'],
        'surcharge over 100' => [['surcharge_percent' => '101'], 'surcharge_percent'],
        'surcharge with 3 decimals' => [['surcharge_percent' => '2.125'], 'surcharge_percent'],
        'unknown account' => [['cash_account_id' => 999999], 'cash_account_id'],
    ]);

    it('blocks changing the type of an account with history, and deleting it', function () {
        $shop = provisionTenant();
        $drawer = inTenant($shop->tenant, $shop->branch, fn () => CashAccount::query()->orderBy('sort')->firstOrFail());
        cash($shop, fn (CashService $cash) => $cash->record($drawer, CashMovementType::CustomerPayment, 1_000));

        $this->actingAs($shop->owner)
            ->put(route('settings.payments.accounts.update', $drawer), ['name' => $drawer->name, 'type' => 'bank'])
            ->assertSessionHas('error', __('cash.type_locked'));
        $this->actingAs($shop->owner)
            ->delete(route('settings.payments.accounts.destroy', $drawer))
            ->assertSessionHas('error', __('cash.account_delete_blocked'));
    });
});
