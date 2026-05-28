<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashboard_section_user_settings')) {
            return;
        }

        Schema::create('dashboard_section_user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('section_key', 80)->index();
            $table->unsignedInteger('refresh_seconds')->default(60);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'section_key'], 'dash_section_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_section_user_settings');
    }
};
