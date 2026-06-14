<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_insight_user_accesses')) {
            Schema::create('ai_insight_user_accesses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true)->index();
                $table->string('access_level')->default('director')->index(); // director, admin
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->index();
                $table->foreignId('updated_by_user_id')->nullable()->index();
                $table->timestamp('last_access_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insight_user_accesses');
    }
};
