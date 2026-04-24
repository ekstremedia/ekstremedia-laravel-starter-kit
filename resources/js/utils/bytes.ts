/**
 * Single byte-formatter used everywhere the UI shows a file or quota
 * size — file listings, usage bars, admin storage dashboard, dashboard
 * cards.
 *
 * Semantics:
 *   - `null` / `undefined` / negative → em dash, representing "unknown"
 *     or "not applicable" (the admin user list uses this for users who
 *     haven't been resolved yet; FilesUsageBar uses it for unlimited).
 *   - `0` → "0 B" (the blocked sentinel for a quota is handled a layer
 *     above this helper — callers that want to show "Disabled" should
 *     branch before calling).
 *   - Positive → highest-fitting unit up to TB, 1-decimal precision
 *     except for raw bytes (where ".0" looks wrong).
 */
export function humanBytes(n: number | null | undefined): string {
    if (n == null || n < 0) return '—';
    if (n === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let value = n;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }
    return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}
