<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_price_list_changes')) {
            return;
        }

        Schema::create('pos_price_list_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('pos_point_id')->nullable()->index();
            $table->unsignedBigInteger('pos_session_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->unsignedBigInteger('previous_price_list_id')->nullable()->index();
            $table->string('previous_price_list_name')->nullable();

            $table->unsignedBigInteger('new_price_list_id')->nullable()->index();
            $table->string('new_price_list_name')->nullable();

            $table->string('source')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['pos_session_id', 'changed_at'], 'pos_pl_changes_session_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_price_list_changes');
    }
};
