/**
 * Stock quantities, mirroring App\Support\Quantity: hundredths as integers, typed with a
 * comma or a dot ("4,2"). Only for showing previews; the server calculates the real totals.
 */

/** "4,2" → 420; null when the text is not a quantity */
export function parseQuantity(input: string): number | null {
    const value = input.trim().replace(',', '.');

    if (!/^\d+(\.\d{1,2})?$/.test(value)) {
        return null;
    }

    const [whole, decimals = ''] = value.split('.');

    return Number(whole) * 100 + Number(decimals.padEnd(2, '0'));
}

/** Price of a quantity in hundredths, rounded half up like Quantity::priceOf() */
export function priceOf(hundredths: number, unitPrice: number): number {
    return Math.floor((hundredths * unitPrice + 50) / 100);
}

/** Preview price of a typed quantity ("4,2") at a unit price; 0 while incomplete */
export function linePrice(qty: string, unitPrice: number | null): number {
    const hundredths = parseQuantity(qty);

    return hundredths === null || unitPrice === null ? 0 : priceOf(hundredths, unitPrice);
}

/** "4.20" → "4,2": prefill inputs the way Indonesian staff type decimals */
export function quantityInput(value: string): string {
    return value.replace('.', ',');
}
