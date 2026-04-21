<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Originally this migration cleared `files_enabled: false` from every
 * user_settings row to unstick the "Files nav hidden for admins" bug. That
 * logic was unsafe: the SettingsController and Admin\UserController::setQuota
 * both let a user/admin legitimately opt out with `files_enabled: false`, and
 * the blanket delete would silently re-enable file access for those opt-outs.
 *
 * The actual root cause — UserSetting::merge() baking the current defaults
 * into the row — is fixed in the model. That fix stops new leakage going
 * forward. Existing rows with `files_enabled: false` are now ambiguous
 * (legacy leak vs. deliberate opt-out), so this migration leaves them alone.
 *
 * The file is kept rather than deleted so environments that already ran the
 * old version don't end up with a "missing" migration record; the up() body
 * is now a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — see class docblock.
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};
