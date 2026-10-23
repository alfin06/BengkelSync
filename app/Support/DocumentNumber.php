<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Models\Branch;
use App\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Race-safe document numbers (CLAUDE.md rule 10): `{BRANCH_CODE}/{TYPE}/{YYYY}/{NNNNN}`.
 *
 * The sequence row is locked with SELECT ... FOR UPDATE until the surrounding transaction
 * commits, so two concurrent invoices can never get the same number. Call this inside the
 * same transaction that saves the document, so a rolled-back save does not burn a number
 * that was already handed out to a committed document.
 */
final class DocumentNumber
{
    public static function next(Branch $branch, DocumentType $type, ?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->copy()->setTimezone($branch->timezone)->year;

        $number = DB::transaction(function () use ($branch, $type, $year): int {
            $now = now();

            DocumentSequence::query()->insertOrIgnore([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->getKey(),
                'type' => $type->value,
                'year' => $year,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DocumentSequence::query()
                ->where('branch_id', $branch->getKey())
                ->where('type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return $sequence->last_number;
        });

        return sprintf('%s/%s/%d/%05d', $branch->code, $type->value, $year, $number);
    }
}
