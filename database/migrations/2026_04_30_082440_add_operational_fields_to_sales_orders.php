<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'delivery_policy')) {
                $table->string('delivery_policy')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'billing_address')) {
                $table->text('billing_address')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'payment_terms')) {
                $table->string('payment_terms')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'fiscal_position')) {
                $table->string('fiscal_position')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'invoice_status')) {
                $table->string('invoice_status')->default('not_invoiced')->index();
            }

            if (! Schema::hasColumn('sales_orders', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->index();
            }

            if (! Schema::hasColumn('sales_orders', 'salesperson_user_id')) {
                $table->unsignedBigInteger('salesperson_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'sales_team')) {
                $table->string('sales_team')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'crm_opportunity_reference')) {
                $table->string('crm_opportunity_reference')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'campaign')) {
                $table->string('campaign')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'medium')) {
                $table->string('medium')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'customer_reference')) {
                $table->string('customer_reference')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
