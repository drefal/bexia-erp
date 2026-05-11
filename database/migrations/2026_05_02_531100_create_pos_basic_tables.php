<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_points')) {
            Schema::create('pos_points', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('stock_location_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->string('status')->default('active')->index();
                $table->string('price_list_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_point_user')) {
            Schema::create('pos_point_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pos_point_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->timestamps();

                $table->unique(['pos_point_id', 'user_id'], 'pos_point_user_unique');
            });
        }

        if (! Schema::hasTable('pos_cashiers')) {
            Schema::create('pos_cashiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('pos_point_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->string('pin_hash')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pos_sessions')) {
            Schema::create('pos_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('pos_point_id')->index();
                $table->unsignedBigInteger('pos_cashier_id')->index();
                $table->unsignedBigInteger('opened_by_user_id')->nullable()->index();
                $table->string('number')->nullable()->index();
                $table->string('status')->default('open')->index();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->decimal('opening_amount', 16, 6)->default(0);
                $table->decimal('closing_amount', 16, 6)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('pos_cashiers');
        Schema::dropIfExists('pos_point_user');
        Schema::dropIfExists('pos_points');
    }
};
