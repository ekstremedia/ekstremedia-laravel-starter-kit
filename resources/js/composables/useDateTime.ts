/**
 * Date/time formatting composable.
 *
 * All timestamps from the API arrive in UTC (ISO 8601 with `Z` or `+00:00`).
 * `Intl.DateTimeFormat` converts them to the browser's local timezone
 * automatically.  Using a shared composable keeps formatting consistent and
 * avoids subtle timezone bugs (e.g. `toLocaleString()` applied to a string
 * that lacks timezone info would silently treat it as local time).
 */

const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;

/**
 * Format an ISO 8601 date string as a localised date + time in the user's
 * browser timezone.  Returns a dash when the value is null/undefined.
 */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '\u2014'; // em-dash
    }

    // Ensure the string is treated as UTC when no offset is present.
    // Carbon normally appends `Z` or `+00:00`, but if the offset is missing
    // (e.g. `2026-04-18T21:23:00`) JavaScript would interpret it as local
    // time, which causes the exact 2-hour drift the user reported.
    let normalised = iso;
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?$/.test(iso)) {
        normalised = iso + 'Z';
    }

    return new Date(normalised).toLocaleString(undefined, {
        timeZone: browserTz,
    });
}

/**
 * Format an ISO 8601 date string as a date-only string (no time) in the
 * user's browser timezone.
 */
export function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '\u2014';
    }

    let normalised = iso;
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?$/.test(iso)) {
        normalised = iso + 'Z';
    }

    return new Date(normalised).toLocaleDateString(undefined, {
        timeZone: browserTz,
    });
}

/**
 * Composable wrapper – handy inside `<script setup>` so templates can call
 * `formatDateTime(value)` without an explicit import of the bare function.
 */
export function useDateTime() {
    return { formatDateTime, formatDate };
}
