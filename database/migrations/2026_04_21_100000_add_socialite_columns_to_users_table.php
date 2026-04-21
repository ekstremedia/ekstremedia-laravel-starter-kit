<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Nullable so the password-based flow keeps working untouched.
            // The (provider, provider_id) pair must be UNIQUE — otherwise
            // two local accounts could both claim the same OAuth identity
            // (one racing the callback of the other).
            $table->string('provider')->nullable()->after('remember_token');
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('provider_avatar_url')->nullable()->after('provider_id');

            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id', 'provider_avatar_url']);
        });
    }
};
