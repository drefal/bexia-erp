<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * BEXIA_V5525C_INVOICE_PAYMENTS_TREASURY_LINK
         * Conecta pagos de factura con movimientos de tesorería.
         */

        if (! Schema::hasTable('invoice_payments')) {
            return;
        }

        Schema::table('invoice_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_payments', 'treasury_movement_id')) {
                $table->unsignedBigInteger('treasury_movement_id')->nullable()->index()->after('source_payment_id');
            }

            if (! Schema::hasColumn('invoice_payments', 'reference')) {
                $table->string('reference')->nullable()->index()->after('payment_form_code');
            }

            if (! Schema::hasColumn('invoice_payments', 'notes')) {
                $table->text('notes')->nullable()->after('reference');
            }

            if (! Schema::hasColumn('invoice_payments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_payments')) {
            return;
        }

        Schema::table('invoice_payments', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_payments', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }

            if (Schema::hasColumn('invoice_payments', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('invoice_payments', 'reference')) {
                $table->dropColumn('reference');
            }

            if (Schema::hasColumn('invoice_payments', 'treasury_movement_id')) {
                $table->dropColumn('treasury_movement_id');
            }
        });
    }
};
