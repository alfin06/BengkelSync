<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\Audit\AuditLogger;

it('records create, update and delete with old and new values', function () {
    $shop = provisionTenant();

    $logs = inTenant($shop->tenant, null, function () {
        $branch = Branch::query()->create(['name' => 'Cabang Baru', 'code' => 'NEW']);
        $branch->update(['name' => 'Cabang Ganti']);
        $branch->delete();

        return AuditLog::query()
            ->where('auditable_type', $branch->getMorphClass())
            ->where('auditable_id', $branch->id)
            ->orderBy('id')
            ->get();
    });

    expect($logs->pluck('action')->all())->toBe(['created', 'updated', 'deleted']);

    [$created, $updated] = [$logs[0], $logs[1]];

    expect($created->new_values)->toMatchArray(['name' => 'Cabang Baru', 'code' => 'NEW'])
        ->and($updated->old_values)->toBe(['name' => 'Cabang Baru'])
        ->and($updated->new_values)->toMatchArray(['name' => 'Cabang Ganti'])
        ->and($updated->new_values)->not->toHaveKey('updated_at');
});

it('never logs hidden attributes such as the password', function () {
    $shop = provisionTenant();
    $user = userWithRole($shop, RoleName::FrontDesk);

    $log = inTenant($shop->tenant, null, fn () => AuditLog::query()
        ->where('auditable_id', $user->id)
        ->where('auditable_type', $user->getMorphClass())
        ->where('action', 'created')
        ->firstOrFail());

    expect($log->new_values)->toHaveKey('email')
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

it('records the user, ip, branch and reason of explicit actions', function () {
    $shop = provisionTenant();

    $this->actingAs($shop->owner);

    $log = inTenant($shop->tenant, $shop->branch, fn () => app(AuditLogger::class)
        ->record('invoice_voided', $shop->branch, ['status' => 'unpaid'], ['status' => 'void'], 'Salah input'));

    expect($log->tenant_id)->toBe($shop->tenant->id)
        ->and($log->user_id)->toBe($shop->owner->id)
        ->and($log->reason)->toBe('Salah input')
        ->and($log->old_values)->toBe(['status' => 'unpaid']);
});

it('skips updates that only touch excluded attributes', function () {
    $shop = provisionTenant();

    $count = inTenant($shop->tenant, null, function () use ($shop) {
        $before = AuditLog::query()->count();
        $shop->owner->forceFill(['last_login_at' => now()])->save();

        return AuditLog::query()->count() - $before;
    });

    expect($count)->toBe(0);
});
