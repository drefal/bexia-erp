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
            if (! Schema::hasColumn('sales_orders', 'quote_validation_status')) {
                $table->string('quote_validation_status', 40)
                    ->default('not_validated')
                    ->index();
            }

            if (! Schema::hasColumn('sales_orders', 'quote_validated_at')) {
                $table->timestamp('quote_validated_at')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'quote_validated_by_user_id')) {
                $table->unsignedBigInteger('quote_validated_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'quote_validation_message')) {
                $table->text('quote_validation_message')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            foreach ([
                'quote_validation_message',
                'quote_validated_by_user_id',
                'quote_validated_at',
                'quote_validation_status',
            ] as $column) {
                if (Schema::hasColumn('sales_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
