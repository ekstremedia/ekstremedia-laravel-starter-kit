<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Global fallback for a user's personal quota when neither the
            // user override nor the tenant default is set. Null = unlimited
            // (no global default set); -1 carries the same "unlimited"
            // meaning for consistency with the tenant/user override sentinels.
            // Signed so callers don't have to special-case the type.
            $table->bigInteger('default_personal_storage_bytes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('default_personal_storage_bytes');
        });
    }
};
