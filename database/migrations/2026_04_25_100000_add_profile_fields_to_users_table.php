<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('id');
            $table->string('headline', 160)->nullable()->after('email');
            $table->text('bio')->nullable()->after('headline');
            $table->string('location', 120)->nullable()->after('bio');
            $table->string('website', 255)->nullable()->after('location');
        });

        // Backfill public_id for existing rows. Done in PHP rather than a
        // single SQL UPDATE so each user gets a fresh UUID v4 instead of all
        // sharing the same generated value.
        DB::table('users')->whereNull('public_id')->orderBy('id')->each(function (object $row): void {
            DB::table('users')->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'headline', 'bio', 'location', 'website']);
        });
    }
};
