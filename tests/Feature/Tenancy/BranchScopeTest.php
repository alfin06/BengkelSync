<?php

use App\Support\Tenancy\TenancyViolation;
use Tests\Fixtures\BranchNote;

// CLAUDE.md rule 2, tested with a fixture model until the first operational model exists.

it('only returns rows of the active branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);

    inTenant($shop->tenant, $shop->branch, fn () => BranchNote::query()->create(['body' => 'pusat']));
    inTenant($shop->tenant, $second, fn () => BranchNote::query()->create(['body' => 'cabang dua']));

    $bodies = inTenant($shop->tenant, $shop->branch, fn () => BranchNote::query()->pluck('body')->all());

    expect($bodies)->toBe(['pusat']);
});

it('fills branch_id and tenant_id from the context', function () {
    $shop = provisionTenant();

    $note = inTenant($shop->tenant, $shop->branch, fn () => BranchNote::query()->create(['body' => 'x']));

    expect($note->branch_id)->toBe($shop->branch->id)
        ->and($note->tenant_id)->toBe($shop->tenant->id);
});

it('throws when branch data is queried without an active branch', function () {
    $shop = provisionTenant();

    inTenant($shop->tenant, null, fn () => BranchNote::query()->get());
})->throws(TenancyViolation::class);

it('refuses to write rows of another branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);

    $note = inTenant($shop->tenant, $second, fn () => BranchNote::query()->create(['body' => 'x']));

    inTenant($shop->tenant, $shop->branch, function () use ($note) {
        $note->body = 'changed';
        $note->save();
    });
})->throws(TenancyViolation::class);

it('refuses to create a row for another branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);

    inTenant($shop->tenant, $shop->branch, fn () => BranchNote::query()->create(['body' => 'x', 'branch_id' => $second->id]));
})->throws(TenancyViolation::class);

it('refuses to move a row to another branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);

    inTenant($shop->tenant, $shop->branch, function () use ($second) {
        $note = BranchNote::query()->create(['body' => 'x']);
        $note->branch_id = $second->id;
        $note->save();
    });
})->throws(TenancyViolation::class);
