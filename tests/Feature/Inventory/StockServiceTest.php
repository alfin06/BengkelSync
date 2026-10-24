<?php

use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\PartBranchStock;
use App\Models\StockMovement;
use App\Notifications\LowStockNotification;
use App\Services\Inventory\StockService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('adds and removes stock, writing one movement per change with the running balance', function () {
    $shop = provisionTenant();
    $part = partIn($shop);

    stock($shop, function (StockService $stock) use ($part) {
        $stock->move($part, StockMovementType::Purchase, 1000, unitCost: 45_000);
        $stock->move($part, StockMovementType::WorkOrderUse, -300);

        expect($stock->available($part))->toBe(700)
            ->and(StockMovement::query()->orderBy('id')->pluck('qty_after')->all())->toBe(['10.00', '7.00']);
    });
});

it('handles decimal quantities for measured units', function () {
    $shop = provisionTenant();
    $oil = partIn($shop, ['unit' => 'liter']);

    stock($shop, function (StockService $stock) use ($oil) {
        $stock->move($oil, StockMovementType::Purchase, 2000);
        $stock->move($oil, StockMovementType::WorkOrderUse, -420);

        expect($stock->available($oil))->toBe(1580);
    });
});

it('only allows whole numbers for counted units', function () {
    $shop = provisionTenant();
    $filter = partIn($shop, ['unit' => 'pcs']);

    stock($shop, fn (StockService $stock) => $stock->move($filter, StockMovementType::Purchase, 150));
})->throws(BusinessRuleViolation::class);

it('blocks using more than is in stock', function () {
    $shop = provisionTenant();
    $part = partIn($shop);

    stock($shop, function (StockService $stock) use ($part) {
        $stock->move($part, StockMovementType::Purchase, 200);

        expect(fn () => $stock->move($part, StockMovementType::WorkOrderUse, -300))
            ->toThrow(BusinessRuleViolation::class, __('stock.insufficient', ['part' => $part->name, 'available' => '2', 'unit' => $part->unit->label()]));

        expect($stock->available($part))->toBe(200)
            ->and(StockMovement::query()->count())->toBe(1);
    });
});

it('keeps stock per branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);
    $part = partIn($shop);

    stock($shop, fn (StockService $stock) => $stock->move($part, StockMovementType::Purchase, 500));
    $inSecond = inTenant($shop->tenant, $second, fn () => app(StockService::class)->available($part));

    expect($inSecond)->toBe(0);
});

it('corrects stock to the counted quantity with a reason, and ignores an unchanged count', function () {
    $shop = provisionTenant();
    $part = partIn($shop);

    stock($shop, function (StockService $stock) use ($part) {
        $stock->move($part, StockMovementType::Purchase, 1000);
        $movement = $stock->adjustTo($part, 800, 'Stock opname Oktober');

        expect($movement?->type)->toBe(StockMovementType::Adjustment)
            ->and($movement?->qty)->toBe('-2.00')
            ->and($movement?->note)->toBe('Stock opname Oktober')
            ->and($stock->adjustTo($part, 800, 'Cek ulang'))->toBeNull()
            ->and(fn () => $stock->adjustTo($part, 500, ' '))->toThrow(BusinessRuleViolation::class);
    });
});

it('notifies branch staff once when stock reaches the threshold', function () {
    Notification::fake();
    $shop = provisionTenant();
    $second = addBranch($shop);
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);
    $otherBranch = userWithRole($shop, RoleName::FrontDesk, [$second]);
    $part = partIn($shop);

    stock($shop, function (StockService $stock) use ($part) {
        $stock->configure($part, 300, 'Rak A3');
        $stock->move($part, StockMovementType::Purchase, 1000);
        $stock->move($part, StockMovementType::WorkOrderUse, -600); // 4: still above
        $stock->move($part, StockMovementType::WorkOrderUse, -200); // 2: crossed → notify
        $stock->move($part, StockMovementType::WorkOrderUse, -100); // 1: already low → no repeat
    });

    Notification::assertSentToTimes($frontDesk, LowStockNotification::class, 1);
    Notification::assertSentTo($shop->owner, LowStockNotification::class);
    Notification::assertNotSentTo($otherBranch, LowStockNotification::class);
});

it('does not notify when no threshold is set', function () {
    Notification::fake();
    $shop = provisionTenant();
    $part = partIn($shop);

    stock($shop, function (StockService $stock) use ($part) {
        $stock->move($part, StockMovementType::Purchase, 100);
        $stock->move($part, StockMovementType::WorkOrderUse, -100);
    });

    Notification::assertNothingSent();
});

it('never lets the database hold negative stock, even when bypassing the service', function () {
    $shop = provisionTenant();
    $part = partIn($shop);
    stock($shop, fn (StockService $stock) => $stock->configure($part, 0, null));

    DB::table('part_branch_stock')->where('part_id', $part->id)->update(['qty' => -1]);
})->throws(QueryException::class);

it('keeps stock movements append-only', function () {
    $shop = provisionTenant();
    $part = partIn($shop);

    stock($shop, function (StockService $stock) use ($part) {
        $movement = $stock->move($part, StockMovementType::Purchase, 100);

        expect(fn () => $movement->forceFill(['note' => 'x'])->save())->toThrow(LogicException::class)
            ->and(fn () => $movement->delete())->toThrow(LogicException::class);
    });
});

it('does not let other code set the quantity by mass assignment', function () {
    $shop = provisionTenant();
    $part = partIn($shop);

    // qty is not fillable; strict mode turns the silent discard into an exception.
    inTenant($shop->tenant, $shop->branch, fn () => new PartBranchStock(['part_id' => $part->id, 'qty' => '99.00']));
})->throws(MassAssignmentException::class);
