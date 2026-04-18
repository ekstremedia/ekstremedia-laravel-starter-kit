<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->index();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('subject');
            $table->string('heading')->nullable();
            $table->text('body');
            $table->string('action_text')->nullable();
            $table->string('action_url')->nullable();
            $table->jsonb('variables')->default('[]');
            $table->mediumText('compiled_html')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
