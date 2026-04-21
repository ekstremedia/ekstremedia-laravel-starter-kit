<?php

/**
 * File-system module configuration.
 *
 * @return array{
 *     gotenberg_url: string,
 *     preview_mime_types: array<int, string>,
 *     trash_retention_days: int,
 *     max_upload_kilobytes: int,
 * }
 */
return [
    'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),

    // Per-file upload size limit applied to files.* validation. Expressed
    // in kilobytes to match Laravel's `max:` validation units.
    'max_upload_kilobytes' => max(1, (int) env('FILES_MAX_UPLOAD_KB', 51200)),

    // Mime types for which we attempt an office→PDF→image preview. Images
    // skip this pipeline — they already have their own medialibrary conversions.
    'preview_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'text/plain',
        'text/rtf',
        'application/rtf',
    ],

    // Clamp to at least 1 — downstream commands subtract this from "now" to
    // compute the cutoff; 0 or negative would hard-delete everything on the
    // next scheduled run.
    'trash_retention_days' => max(1, (int) env('FILES_TRASH_DAYS', 30)),
];
