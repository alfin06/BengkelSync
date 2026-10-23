import { getActiveLanguage } from 'laravel-vue-i18n';

/**
 * "2026-10-25" → "25 Okt 2026" (or "25 Oct 2026" in English).
 * Date-only values are formatted as calendar dates, without timezone shifts.
 */
export function formatDate(isoDate: string | null | undefined): string {
    if (!isoDate) {
        return '';
    }

    const [year, month, day] = isoDate.slice(0, 10).split('-').map(Number);
    const date = new Date(Date.UTC(year ?? 1970, (month ?? 1) - 1, day ?? 1));

    return new Intl.DateTimeFormat(getActiveLanguage() === 'en' ? 'en-GB' : 'id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

/** 45000 → "45.000" */
export function formatNumber(value: number | null | undefined): string {
    return value === null || value === undefined ? '' : new Intl.NumberFormat('id-ID').format(value);
}
