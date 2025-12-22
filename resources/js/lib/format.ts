/**
 * Formatters reused across pages (presentation-only).
 */

/**
 * Format quantity numbers for UI.
 *
 * Goals:
 * - Avoid noisy trailing zeros ("13.000" -> "13")
 * - Keep meaningful decimals ("13.25" -> "13.25")
 * - Work with numbers and numeric strings
 */
export function formatQty(
    value: number | string | null | undefined,
    opts?: { maxDecimals?: number },
) {
    if (value === null || value === undefined || value === '') return '-';

    const n =
        typeof value === 'number'
            ? value
            : Number(String(value).replace(',', '.'));
    if (!Number.isFinite(n)) return String(value);

    const maxDecimals = opts?.maxDecimals ?? 3;

    // Format with maxDecimals, then trim trailing zeros/dot.
    const str = n
        .toFixed(maxDecimals)
        .replace(/\.0+$/, '')
        .replace(/(\.[0-9]*?)0+$/, '$1');

    return str;
}

/**
 * Format currency numbers for UI.
 *
 * Uses Intl.NumberFormat with sensible defaults for IDR and other currencies.
 */
export function formatCurrency(
    value: number | string | null | undefined,
    opts?: {
        currency?: string;
        locale?: string;
        minimumFractionDigits?: number;
        maximumFractionDigits?: number;
    },
) {
    if (value === null || value === undefined || value === '') return '-';

    const n =
        typeof value === 'number'
            ? value
            : Number(String(value).replace(',', '.'));

    if (!Number.isFinite(n)) return String(value);

    const currency = (opts?.currency ?? 'IDR').toUpperCase();
    const locale = opts?.locale ?? 'id-ID';

    // Common Indonesian convention: IDR shown with no decimals.
    const defaultFraction = currency === 'IDR' ? 0 : 2;
    const minimumFractionDigits =
        opts?.minimumFractionDigits ?? defaultFraction;
    const maximumFractionDigits =
        opts?.maximumFractionDigits ?? defaultFraction;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits,
        maximumFractionDigits,
    }).format(n);
}

export function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}
