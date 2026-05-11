<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_audit_logs')) {
            return;
        }

        Schema::create('pos_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->unsignedBigInteger('pos_session_id')->nullable()->index();
            $table->unsignedBigInteger('pos_order_id')->nullable()->index();
            $table->unsignedBigInteger('pos_order_refund_id')->nullable()->index();
            $table->unsignedBigInteger('stock_movement_id')->nullable()->index();

            $table->string('action', 120)->index();
            $table->string('entity_type', 120)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();

            $table->string('description')->nullable();

            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('metadata')->nullable();

            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_audit_logs');
    }
};
