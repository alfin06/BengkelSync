/**
 * Preview of invoice totals, mirroring App\Services\Invoicing\InvoiceCalculator.
 * Only for showing the total while typing; the server calculates the real one.
 */

/** "11.50" → 1150, like Money::basisPoints() */
function basisPoints(rate: string): number {
    const [whole = '0', fraction = ''] = rate.split('.');

    return Number(whole) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2));
}

/** Integer division rounded half up (amounts here are never negative) */
function divideHalfUp(dividend: number, divisor: number): number {
    return Math.floor((2 * dividend + divisor) / (2 * divisor));
}

export interface InvoiceTotals {
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
}

export function invoiceTotals(lineTotals: number[], discount: number, taxEnabled: boolean, rate: string, inclusive: boolean): InvoiceTotals {
    const subtotal = lineTotals.reduce((sum, value) => sum + Math.max(value, 0), 0);
    const safeDiscount = Math.min(Math.max(discount, 0), subtotal);
    const base = subtotal - safeDiscount;

    if (!taxEnabled) {
        return { subtotal, discount: safeDiscount, tax: 0, total: base };
    }

    const points = basisPoints(rate);

    if (inclusive) {
        return { subtotal, discount: safeDiscount, tax: divideHalfUp(base * points, 10_000 + points), total: base };
    }

    const tax = divideHalfUp(base * points, 10_000);

    return { subtotal, discount: safeDiscount, tax, total: base + tax };
}
