<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order_status_logs')) {
            Schema::create('purchase_order_status_logs', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();

                $table->string('event', 80)->index();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['purchase_order_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_status_logs');
    }
};
