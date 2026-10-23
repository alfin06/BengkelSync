<?php

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Support\DocumentNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('formats numbers as BRANCH/TYPE/YEAR/NNNNN and counts up', function () {
    $shop = provisionTenant();
    Carbon::setTestNow('2026-05-10 03:00:00');

    $numbers = inTenant($shop->tenant, $shop->branch, fn () => [
        DocumentNumber::next($shop->branch, DocumentType::Invoice),
        DocumentNumber::next($shop->branch, DocumentType::Invoice),
        DocumentNumber::next($shop->branch, DocumentType::WorkOrder),
    ]);

    $code = $shop->branch->code;

    expect($numbers)->toBe([
        "{$code}/INV/2026/00001",
        "{$code}/INV/2026/00002",
        "{$code}/WO/2026/00001",
    ]);
});

it('keeps separate sequences per branch', function () {
    $shop = provisionTenant();
    $second = addBranch($shop);

    $numbers = inTenant($shop->tenant, null, fn () => [
        DocumentNumber::next($shop->branch, DocumentType::Invoice),
        DocumentNumber::next($second, DocumentType::Invoice),
    ]);

    expect($numbers[0])->toEndWith('/00001')->and($numbers[1])->toEndWith('/00001');
});

it('starts again at 1 in a new year, using the branch timezone', function () {
    $shop = provisionTenant();

    // 31 Dec 2026 17:30 UTC is already 1 Jan 2027 00:30 in Jakarta (UTC+7).
    $numbers = inTenant($shop->tenant, null, fn () => [
        DocumentNumber::next($shop->branch, DocumentType::Invoice, Carbon::parse('2026-12-31 16:00:00', 'UTC')),
        DocumentNumber::next($shop->branch, DocumentType::Invoice, Carbon::parse('2026-12-31 17:30:00', 'UTC')),
    ]);

    expect($numbers[0])->toContain('/2026/00001')
        ->and($numbers[1])->toContain('/2027/00001');
});

it('cannot number documents for a branch of another tenant', function () {
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    inTenant($a->tenant, null, fn () => DocumentNumber::next($b->branch, DocumentType::Invoice));
})->throws(Exception::class);

it('locks the sequence row so concurrent requests wait instead of reusing a number', function () {
    $shop = provisionTenant();
    $year = (int) now()->setTimezone($shop->branch->timezone)->year;

    // Connection 1 (the test transaction) hands out a number and keeps the row locked.
    inTenant($shop->tenant, null, fn () => DocumentNumber::next($shop->branch, DocumentType::Invoice));

    // Connection 2 tries to lock the same row, like a second request at the same moment.
    config(['database.connections.mysql_second' => config('database.connections.mysql')]);
    $second = DB::connection('mysql_second');
    $second->statement('SET SESSION innodb_lock_wait_timeout = 1');

    try {
        expect(fn () => $second->table((new DocumentSequence)->getTable())
            ->where('branch_id', $shop->branch->id)
            ->where('type', DocumentType::Invoice->value)
            ->where('year', $year)
            ->lockForUpdate()
            ->first())
            ->toThrow(QueryException::class, 'Lock wait timeout');
    } finally {
        DB::purge('mysql_second');
    }
});
