<?php

namespace App\Support;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Times are stored in UTC and typed and shown in the active branch's timezone (rule 11).
 */
final class BranchTime
{
    /**
     * A time typed in a datetime-local input ("2026-10-24T15:30", branch time) → UTC.
     */
    public static function fromInput(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value, self::timezone())->utc()->toDateTimeString();
    }

    /**
     * A stored UTC time → the value of a datetime-local input, in branch time.
     */
    public static function toInput(?Carbon $value): ?string
    {
        return $value?->copy()->setTimezone(self::timezone())->format('Y-m-d\TH:i');
    }

    /**
     * A stored UTC time → "24 Okt 2026, 15:30" in branch time and the reader's language.
     */
    public static function label(?Carbon $value, string $format = 'j M Y, H:i'): ?string
    {
        return $value?->copy()->setTimezone(self::timezone())->translatedFormat($format);
    }

    private static function timezone(): string
    {
        return app(TenantContext::class)->timezone();
    }
}
