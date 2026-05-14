<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * BEXIA_V5526B_GLOBAL_INVOICE_TICKETS
         * Relación trazable entre factura global y tickets PDV incluidos.
         */

        if (! Schema::hasTable('global_invoice_tickets')) {
            Schema::create('global_invoice_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('pos_order_id')->index();
                $table->string('ticket_number')->nullable()->index();
                $table->timestamp('ticket_date')->nullable()->index();
                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('tax_total', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->string('payment_summary')->nullable();
                $table->string('status')->default('draft')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'pos_order_id'], 'global_invoice_tickets_company_order_unique');
            });
        }

        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_orders', 'global_invoice_id')) {
                    $table->unsignedBigInteger('global_invoice_id')->nullable()->index();
                }

                if (! Schema::hasColumn('pos_orders', 'global_invoiced_at')) {
                    $table->timestamp('global_invoiced_at')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                if (Schema::hasColumn('pos_orders', 'global_invoiced_at')) {
                    $table->dropColumn('global_invoiced_at');
                }

                if (Schema::hasColumn('pos_orders', 'global_invoice_id')) {
                    $table->dropColumn('global_invoice_id');
                }
            });
        }

        Schema::dropIfExists('global_invoice_tickets');
    }
};
