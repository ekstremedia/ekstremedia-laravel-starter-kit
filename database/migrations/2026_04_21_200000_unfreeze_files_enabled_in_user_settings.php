<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove legacy `files_enabled: false` entries that leaked into user_settings
 * before the default was flipped to true.
 *
 * No user-facing UI has ever written to this key, so any stored value is the
 * old default being frozen in by UserSetting::merge() (now fixed). Wiping the
 * key from the JSON blob lets the fresh default take over for every affected
 * row. Explicit opt-outs from tests that merge `files_enabled: true` are left
 * alone — we only drop the false variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Portable path: load, rewrite, save. Keeps the migration working on
        // sqlite (used by the test suite) where JSONB operators don't exist.
        DB::table('user_settings')
            ->select('id', 'settings')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->settings, true) ?: [];
                    if (! is_array($decoded) || ! array_key_exists('files_enabled', $decoded)) {
                        continue;
                    }
                    if ($decoded['files_enabled'] === false) {
                        unset($decoded['files_enabled']);
                        DB::table('user_settings')
                            ->where('id', $row->id)
                            ->update(['settings' => json_encode($decoded)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Irreversible — the old default was a bug. Nothing to restore.
    }
};
