<?php

namespace App\Services\Invoicing;

use App\Support\Money;
use App\Support\Quantity;

/**
 * What changed between two versions of an invoice (SPEC section 6, "compare revisions"):
 * lines added, removed or changed (quantity, price, discount), and the totals. Lines are
 * matched by part, or by type and description for services and other charges.
 */
final class RevisionComparer
{
    /**
     * @param  array<string, mixed>  $before  a snapshot (see InvoiceService::snapshot())
     * @param  array<string, mixed>  $after  a snapshot of the next version
     * @return array{lines: list<array{change: string, description: string, before: string|null, after: string|null}>, totals: list<array{field: string, before: string, after: string}>}
     */
    public static function compare(array $before, array $after): array
    {
        $old = self::keyed($before['lines'] ?? []);
        $new = self::keyed($after['lines'] ?? []);
        $lines = [];

        foreach ($old as $key => $line) {
            if (! isset($new[$key])) {
                $lines[] = ['change' => 'removed', 'description' => $line['description'], 'before' => self::describe($line), 'after' => null];
            } elseif (self::describe($line) !== self::describe($new[$key])) {
                $lines[] = ['change' => 'changed', 'description' => $line['description'], 'before' => self::describe($line), 'after' => self::describe($new[$key])];
            }
        }

        foreach ($new as $key => $line) {
            if (! isset($old[$key])) {
                $lines[] = ['change' => 'added', 'description' => $line['description'], 'before' => null, 'after' => self::describe($line)];
            }
        }

        $totals = [];
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'total'] as $field) {
            if (($before[$field] ?? 0) !== ($after[$field] ?? 0)) {
                $totals[] = ['field' => $field, 'before' => Money::format((int) ($before[$field] ?? 0)), 'after' => Money::format((int) ($after[$field] ?? 0))];
            }
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, array<string, mixed>>
     */
    private static function keyed(array $lines): array
    {
        $keyed = [];
        foreach ($lines as $line) {
            $key = $line['part_id'] !== null ? 'part:'.$line['part_id'] : $line['type'].':'.mb_strtolower(trim((string) $line['description']));
            // The same part twice on one invoice: keep both apart.
            $suffix = '';
            while (isset($keyed[$key.$suffix])) {
                $suffix .= '#';
            }
            $keyed[$key.$suffix] = $line;
        }

        return $keyed;
    }

    /**
     * "2 pcs × Rp 45.000 − Rp 5.000 = Rp 85.000"
     *
     * @param  array<string, mixed>  $line
     */
    private static function describe(array $line): string
    {
        $qty = Quantity::format(Quantity::fromDecimal((string) $line['qty']));
        $unit = isset($line['unit_label']) ? ' '.$line['unit_label'] : '';
        $discount = (int) ($line['discount_amount'] ?? 0) > 0 ? ' − '.Money::format((int) $line['discount_amount']) : '';

        return $qty.$unit.' × '.Money::format((int) $line['unit_price']).$discount.' = '.Money::format((int) $line['line_total']);
    }
}
