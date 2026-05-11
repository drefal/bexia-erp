<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bexia_notifications')) {
            Schema::create('bexia_notifications', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();

                $table->string('type', 80)->nullable()->index();
                $table->string('title', 180);
                $table->text('body')->nullable();
                $table->string('url', 600)->nullable();

                $table->json('metadata')->nullable();
                $table->timestamp('read_at')->nullable()->index();

                $table->timestamps();

                $table->index(['user_id', 'read_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (Schema::hasTable('approval_request_steps') && ! Schema::hasColumn('approval_request_steps', 'decision_reason')) {
            Schema::table('approval_request_steps', function (Blueprint $table) {
                $table->text('decision_reason')->nullable();
            });
        }

        if (Schema::hasTable('approval_requests') && ! Schema::hasColumn('approval_requests', 'last_decision_reason')) {
            Schema::table('approval_requests', function (Blueprint $table) {
                $table->text('last_decision_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        // No se eliminan columnas ni tabla para no perder historial operativo.
    }
};
