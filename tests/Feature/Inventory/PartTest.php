<?php

use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Part;
use App\Services\Inventory\StockService;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

function validPart(array $overrides = []): array
{
    return [
        'sku' => 'of-1001',
        'name' => 'Oli Shell Helix 10W-40',
        'brand' => 'Shell',
        'category' => 'Oli',
        'unit' => 'liter',
        'cost_price' => 55000,
        'sell_price' => 70000,
        'is_active' => true,
        'notes' => null,
        ...$overrides,
    ];
}

it('creates a part with opening stock, threshold and location in the active branch', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('parts.store'), validPart(['opening_stock' => '20,5', 'min_stock' => '5', 'location' => 'Rak A3']))
        ->assertSessionHasNoErrors();

    inTenant($shop->tenant, $shop->branch, function () {
        $part = Part::query()->with('stock')->firstOrFail();

        expect($part->sku)->toBe('OF-1001')
            ->and($part->stock?->qty)->toBe('20.50')
            ->and($part->stock?->min_stock)->toBe('5.00')
            ->and($part->stock?->location)->toBe('Rak A3')
            ->and($part->movements()->first()?->note)->toBe(__('stock.opening_balance'));
    });
});

it('rejects decimal quantities for counted units', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('parts.store'), validPart(['unit' => 'pcs', 'opening_stock' => '2,5']))
        ->assertSessionHasErrors('opening_stock');
});

it('keeps SKUs unique within the tenant, including deleted parts', function () {
    $shop = provisionTenant();
    $part = partIn($shop, ['sku' => 'OF-1001']);
    inTenant($shop->tenant, null, fn () => $part->delete());

    $this->actingAs($shop->owner)->post(route('parts.store'), validPart())->assertSessionHasErrors('sku');
    $this->actingAs(provisionTenant('Bengkel Lain')->owner)->post(route('parts.store'), validPart())->assertSessionHasNoErrors();
});

it('does not let front desk staff set opening stock or see costs', function () {
    $shop = provisionTenant();
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($frontDesk)->post(route('parts.store'), validPart(['opening_stock' => '5']))->assertSessionHasErrors('opening_stock');

    $this->actingAs($frontDesk)->post(route('parts.store'), validPart())->assertSessionHasNoErrors();
    $part = inTenant($shop->tenant, null, fn () => Part::query()->firstOrFail());

    expect($part->cost_price)->toBe(0); // the cost they sent was ignored

    $this->actingAs($frontDesk)->get(route('parts.show', $part))
        ->assertInertia(fn (Assert $page) => $page->where('part.cost_price', null)->where('can.adjustStock', false));
});

it('corrects stock through the stock opname form, with a reason', function () {
    $shop = provisionTenant();
    $part = partIn($shop, ['unit' => 'liter']);
    stock($shop, fn (StockService $stock) => $stock->move($part, StockMovementType::Purchase, 1000));

    $this->actingAs($shop->owner)
        ->post(route('parts.stock.adjust', $part), ['counted' => '9,5', 'reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs($shop->owner)
        ->post(route('parts.stock.adjust', $part), ['counted' => '9,5', 'reason' => 'Bocor'])
        ->assertSessionHas('success');

    expect(stock($shop, fn (StockService $stock) => $stock->available($part)))->toBe(950);
});

it('only lets users with stock.adjust correct stock', function () {
    $shop = provisionTenant();
    $part = partIn($shop);

    $this->actingAs(userWithRole($shop, RoleName::FrontDesk))
        ->post(route('parts.stock.adjust', $part), ['counted' => '1', 'reason' => 'x'])
        ->assertForbidden();
});

it('lists parts with their branch stock and filters low stock', function () {
    $shop = provisionTenant();
    $low = partIn($shop, ['name' => 'Kampas rem']);
    $fine = partIn($shop, ['name' => 'Busi']);
    stock($shop, function (StockService $stock) use ($low, $fine) {
        $stock->configure($low, 500, null);
        $stock->move($low, StockMovementType::Purchase, 300);
        $stock->configure($fine, 200, null);
        $stock->move($fine, StockMovementType::Purchase, 1000);
    });

    $this->actingAs($shop->owner)->get(route('parts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('parts.total', 2)
            ->where('lowStockCount', 1)
            ->where('parts.data.0.name', 'Busi')
            ->where('parts.data.0.stock.qty_label', '10'));

    $this->actingAs($shop->owner)->get(route('parts.index', ['status' => 'low']))
        ->assertInertia(fn (Assert $page) => $page->where('parts.total', 1)->where('parts.data.0.stock.is_low', true));
});

it('blocks deleting a part with stock history, but allows deactivating it', function () {
    $shop = provisionTenant();
    $part = partIn($shop);
    stock($shop, fn (StockService $stock) => $stock->move($part, StockMovementType::Purchase, 100));

    $this->actingAs($shop->owner)->delete(route('parts.destroy', $part))->assertSessionHas('error', __('parts.delete_has_history'));

    $this->actingAs($shop->owner)
        ->put(route('parts.update', $part), validPart(['sku' => $part->sku, 'unit' => 'pcs', 'is_active' => false]))
        ->assertSessionHasNoErrors();

    expect($part->fresh()?->is_active)->toBeFalse();
});

it('shows low-stock notifications to their own user only', function () {
    $shop = provisionTenant();
    $part = partIn($shop);
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);
    stock($shop, function (StockService $stock) use ($part) {
        $stock->configure($part, 200, null);
        $stock->move($part, StockMovementType::Purchase, 500);
        $stock->move($part, StockMovementType::WorkOrderUse, -300);
    });

    $this->actingAs($frontDesk)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.total', 1)
            ->where('items.data.0.read', false)
            ->where('items.data.0.title', __('notifications.low_stock.title', ['part' => $part->name]))
            ->where('notifications.unread', 1));

    $own = DatabaseNotification::query()->where('notifiable_id', $frontDesk->id)->firstOrFail();
    $ownersNotification = DatabaseNotification::query()->where('notifiable_id', $shop->owner->id)->firstOrFail();

    $this->actingAs($frontDesk)->post(route('notifications.read', $ownersNotification->id))->assertNotFound();
    $this->actingAs($frontDesk)->post(route('notifications.read', $own->id))->assertRedirect(route('parts.show', $part));

    expect($own->fresh()?->read_at)->not->toBeNull();
});

it('applies part permissions and tenant isolation', function () {
    $shop = provisionTenant();
    $part = partIn($shop);

    $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->put(route('parts.update', $part), validPart())->assertForbidden();
    $this->actingAs(userWithRole($shop, RoleName::Warehouse))->put(route('parts.update', $part), validPart())->assertRedirect();
    $this->actingAs(provisionTenant('Bengkel Lain')->owner)->get(route('parts.show', $part))->assertNotFound();
});
