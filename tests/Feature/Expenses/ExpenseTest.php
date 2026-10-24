<?php

use App\Enums\CashMovementType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Cash\CashService;
use App\Services\Expenses\ExpenseService;
use App\Services\Tenancy\ProvisionedTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(config('filesystems.private_disk'));
});

function pettyCash(ProvisionedTenant $shop, int $balance = 300_000): CashAccount
{
    return inTenant($shop->tenant, $shop->branch, function () use ($balance) {
        $petty = CashAccount::query()->where('type', 'petty_cash')->firstOrFail();
        if ($balance > 0) {
            app(CashService::class)->record($petty, CashMovementType::TransferIn, $balance);
        }

        return $petty;
    });
}

function categoryNamed(ProvisionedTenant $shop, string $name): ExpenseCategory
{
    return inTenant($shop->tenant, null, fn () => ExpenseCategory::query()->where('name', $name)->firstOrFail());
}

/**
 * @return array<string, mixed>
 */
function expenseInput(ProvisionedTenant $shop, CashAccount $account, array $overrides = []): array
{
    return [
        'expense_date' => now('Asia/Jakarta')->toDateString(),
        'category_id' => categoryNamed($shop, 'Bensin & transport')->id,
        'description' => 'Bensin motor antar part',
        'amount' => 50_000,
        'cash_account_id' => $account->id,
        ...$overrides,
    ];
}

it('gives every new shop the default categories, with salaries restricted', function () {
    $shop = provisionTenant();

    $categories = inTenant($shop->tenant, null, fn () => ExpenseCategory::query()->orderBy('sort')->get());

    expect($categories->pluck('name')->all())->toBe(['Bensin & transport', 'Makan & minum', 'ATK & kebersihan', 'Listrik, air & internet', 'Perawatan alat', 'Gaji & bonus', 'Lain-lain'])
        ->and($categories->firstWhere('name', 'Gaji & bonus')?->is_restricted)->toBeTrue();
});

describe('recording', function () {
    it('takes the money out of the chosen account and keeps the receipt photo privately', function () {
        $shop = provisionTenant();
        $petty = pettyCash($shop);

        $this->actingAs(userWithRole($shop, RoleName::Finance))->post(route('expenses.store'), [
            ...expenseInput($shop, $petty),
            'receipt' => UploadedFile::fake()->image('nota.jpg'),
        ])->assertSessionHasNoErrors();

        $expense = inTenant($shop->tenant, $shop->branch, fn () => Expense::query()->sole());

        expect(inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->balance($petty)))->toBe(250_000)
            ->and($expense->attachment_path)->not->toBeNull()
            ->and(Storage::disk(config('filesystems.private_disk'))->exists((string) $expense->attachment_path))->toBeTrue()
            ->and(inTenant($shop->tenant, $shop->branch, fn () => CashMovement::query()->where('type', 'expense')->value('note')))->toBe('Bensin & transport: Bensin motor antar part');
    });

    it('never lets petty cash go below zero', function () {
        $shop = provisionTenant();
        $petty = pettyCash($shop, 30_000);

        $this->actingAs(userWithRole($shop, RoleName::Finance))->post(route('expenses.store'), expenseInput($shop, $petty))
            ->assertSessionHasErrors('business');

        expect(inTenant($shop->tenant, $shop->branch, fn () => Expense::query()->count()))->toBe(0);
    });

    it('lets Front Desk record small expenses from cash only, up to the branch limit', function () {
        $shop = provisionTenant();
        $petty = pettyCash($shop, 1_000_000);
        $bank = accountIn($shop, 'bank');
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);

        $this->actingAs($frontDesk)->post(route('expenses.store'), expenseInput($shop, $petty))->assertSessionHasNoErrors();
        $this->actingAs($frontDesk)->post(route('expenses.store'), expenseInput($shop, $bank))->assertSessionHasErrors('business');
        // Default limit Rp 500.000.
        $this->actingAs($frontDesk)->post(route('expenses.store'), expenseInput($shop, $petty, ['amount' => 600_000]))->assertSessionHasErrors('business');

        expect(inTenant($shop->tenant, $shop->branch, fn () => Expense::query()->count()))->toBe(1);
    });
});

describe('restricted categories (salaries)', function () {
    it('are only for the owner: hidden from lists and totals, and refused for others', function () {
        $shop = provisionTenant();
        $petty = pettyCash($shop, 5_000_000);
        $salary = categoryNamed($shop, 'Gaji & bonus');
        $finance = userWithRole($shop, RoleName::Finance);

        $this->actingAs($finance)->post(route('expenses.store'), expenseInput($shop, $petty, ['category_id' => $salary->id, 'amount' => 2_000_000]))
            ->assertSessionHasErrors('business');

        $this->actingAs($shop->owner)->post(route('expenses.store'), expenseInput($shop, $petty, ['category_id' => $salary->id, 'description' => 'Gaji Joko', 'amount' => 2_000_000]))
            ->assertSessionHasNoErrors();
        $this->actingAs($shop->owner)->post(route('expenses.store'), expenseInput($shop, $petty))->assertSessionHasNoErrors();

        $paid = inTenant($shop->tenant, $shop->branch, fn () => Expense::query()->where('category_id', $salary->id)->sole());

        $this->actingAs($finance)->get(route('expenses.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenses.data', 1)
                ->where('totalLabel', 'Rp 50.000')
                ->where('categories', fn ($categories) => ! collect($categories)->contains('name', 'Gaji & bonus')));
        $this->actingAs($shop->owner)->get(route('expenses.index'))
            ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 2)->where('totalLabel', 'Rp 2.050.000'));

        $this->actingAs($finance)->post(route('expenses.void', $paid), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($userWithRole = userWithRole($shop, RoleName::BranchManager))->get(route('expenses.receipt', $paid))->assertForbidden();

        // The cash history does not show what the salary was for.
        expect(inTenant($shop->tenant, $shop->branch, fn () => CashMovement::query()->where('reference_id', $paid->id)->where('reference_type', $paid->getMorphClass())->value('note')))
            ->not->toContain('Joko');
    });

    it('can only be made restricted, or changed, by someone who may see them', function () {
        $shop = provisionTenant();
        $finance = userWithRole($shop, RoleName::Finance);

        $this->actingAs($finance)->post(route('expense-categories.store'), ['name' => 'Galon', 'is_restricted' => false])->assertSessionHasNoErrors();
        $this->actingAs($finance)->post(route('expense-categories.store'), ['name' => 'THR', 'is_restricted' => true])->assertSessionHasErrors('business');
        $this->actingAs($finance)->put(route('expense-categories.update', categoryNamed($shop, 'Gaji & bonus')), ['name' => 'Gaji', 'is_restricted' => false])->assertSessionHasErrors('business');
        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->post(route('expense-categories.store'), ['name' => 'Parkir'])->assertForbidden();
    });
});

describe('corrections', function () {
    it('edits the details with an audit trail, and voids with the money going back', function () {
        $shop = provisionTenant();
        $petty = pettyCash($shop);
        $finance = userWithRole($shop, RoleName::Finance);
        $this->actingAs($finance)->post(route('expenses.store'), expenseInput($shop, $petty));
        $expense = inTenant($shop->tenant, $shop->branch, fn () => Expense::query()->sole());

        $this->actingAs($finance)->put(route('expenses.update', $expense), [
            'expense_date' => now('Asia/Jakarta')->toDateString(),
            'category_id' => categoryNamed($shop, 'Makan & minum')->id,
            'description' => 'Makan siang',
            'amount' => 1,
        ])->assertSessionHasErrors('amount');

        $this->actingAs($finance)->put(route('expenses.update', $expense), [
            'expense_date' => now('Asia/Jakarta')->toDateString(),
            'category_id' => categoryNamed($shop, 'Makan & minum')->id,
            'description' => 'Makan siang',
        ])->assertSessionHasNoErrors();

        $this->actingAs($finance)->post(route('expenses.void', $expense), ['reason' => 'Dobel catat'])->assertSessionHasNoErrors();

        expect($expense->fresh()?->description)->toBe('Makan siang')
            ->and($expense->fresh()?->voided_at)->not->toBeNull()
            ->and(inTenant($shop->tenant, $shop->branch, fn () => app(CashService::class)->balance($petty)))->toBe(300_000)
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'expense_voided')->value('reason')))->toBe('Dobel catat')
            ->and(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(ExpenseService::class)->void($shop->owner, $expense->fresh(), 'Lagi')))->toThrow(BusinessRuleViolation::class);
    });

    it('keeps expenses to their branch and shop', function () {
        $shop = provisionTenant();
        $petty = pettyCash($shop);
        $this->actingAs($shop->owner)->post(route('expenses.store'), expenseInput($shop, $petty));
        $expense = inTenant($shop->tenant, $shop->branch, fn () => Expense::query()->sole());

        $east = addBranch($shop);
        $this->actingAs(userWithRole($shop, RoleName::Finance, [$east]))->post(route('expenses.void', $expense), ['reason' => 'x'])->assertNotFound();
        $this->actingAs(provisionTenant('Bengkel Lain')->owner)->get(route('expenses.receipt', $expense))->assertNotFound();
    });
});

it('keeps expenses and categories isolated per tenant', function () {
    assertTenantIsolated(ExpenseCategory::class, fn () => ExpenseCategory::query()->create(['name' => 'Parkir']));
    assertTenantIsolated(Expense::class, fn (ProvisionedTenant $shop) => app(ExpenseService::class)->create($shop->owner, expenseInput($shop, pettyCash($shop))));
});
