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
