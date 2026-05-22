<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_quote_pos_tickets')) {
            Schema::create('sales_quote_pos_tickets', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->index();

                $table->unsignedBigInteger('sales_order_id')->index();
                $table->unsignedBigInteger('pos_order_id')->nullable()->index();

                $table->unsignedBigInteger('pos_point_id')->index();
                $table->unsignedBigInteger('pos_session_id')->nullable()->index();

                $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                $table->unsignedBigInteger('stock_location_id')->nullable()->index();

                $table->string('status', 40)->default('pending')->index();

                $table->string('public_token', 80)->unique();

                $table->unsignedBigInteger('sent_by_user_id')->nullable()->index();
                $table->timestamp('sent_at')->nullable();

                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('expired_at')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'sales_order_id']);
                $table->index(['company_id', 'pos_point_id', 'status']);
                $table->index(['company_id', 'public_token']);
            });
        }

        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_orders', 'source_type')) {
                    $table->string('source_type', 60)->nullable()->index();
                }

                if (! Schema::hasColumn('pos_orders', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }

                if (! Schema::hasColumn('pos_orders', 'source_reference')) {
                    $table->string('source_reference')->nullable()->index();
                }

                if (! Schema::hasColumn('pos_orders', 'source_public_token')) {
                    $table->string('source_public_token', 80)->nullable()->index();
                }

                if (! Schema::hasColumn('pos_orders', 'source_price_policy')) {
                    $table->string('source_price_policy', 60)->nullable()->index();
                }

                if (! Schema::hasColumn('pos_orders', 'source_locked_prices')) {
                    $table->boolean('source_locked_prices')->default(false)->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                foreach ([
                    'source_locked_prices',
                    'source_price_policy',
                    'source_public_token',
                    'source_reference',
                    'source_id',
                    'source_type',
                ] as $column) {
                    if (Schema::hasColumn('pos_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('sales_quote_pos_tickets');
    }
};
