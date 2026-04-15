<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            // Access gates
            $table->boolean('site_up')->default(true);
            $table->boolean('registration_open')->default(true);
            $table->boolean('login_enabled')->default(true);
            $table->boolean('require_email_verification')->default(true);
            // Policies
            $table->string('default_role', 50)->default('User');
            $table->boolean('require_2fa_for_admins')->default(false);
            $table->boolean('send_welcome_notification')->default(true);
            // Presentation
            $table->string('maintenance_message', 500)->nullable();
            $table->string('announcement_banner', 500)->nullable();
            $table->string('announcement_severity', 20)->default('info'); // info|warn|danger|success
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
