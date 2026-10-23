/**
 * Display helpers for integer Rupiah, mirroring App\Support\Money.
 *
 * Only for showing and typing amounts. Totals, tax and surcharges are always calculated
 * on the server (SPEC section 8.2); never send a computed total to the server.
 */

const grouping = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

/** 1250000 → "Rp 1.250.000" */
export function formatRupiah(amount: number | null | undefined): string {
    const value = Math.trunc(amount ?? 0);
    const sign = value < 0 ? '-' : '';

    return `${sign}Rp ${grouping.format(Math.abs(value))}`;
}

/** 1250000 → "1.250.000" (for inputs, without the currency prefix) */
export function formatThousands(amount: number | null | undefined): string {
    if (amount === null || amount === undefined) {
        return '';
    }

    return grouping.format(Math.trunc(amount));
}

/** "Rp 1.250.000" → 1250000. Anything that is not a digit is ignored; empty input → null. */
export function parseRupiah(input: string): number | null {
    const digits = input.replace(/,\d{0,2}$/, '').replace(/\D/g, '');

    if (digits === '') {
        return null;
    }

    const value = Number(digits);

    return Number.isSafeInteger(value) ? value : null;
}
