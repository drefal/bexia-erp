<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pos_order_lines', 'pos_order_refund_lines', 'pos_order_refunds'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'accounting_status')) {
                    $table->string('accounting_status', 30)->default('not_posted')->index();
                }

                if (! Schema::hasColumn($tableName, 'accounting_entry_id')) {
                    $table->foreignId('accounting_entry_id')
                        ->nullable()
                        ->constrained('accounting_entries')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'accounting_posted_at')) {
                    $table->timestamp('accounting_posted_at')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'accounting_error_message')) {
                    $table->text('accounting_error_message')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['pos_order_lines', 'pos_order_refund_lines', 'pos_order_refunds'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'accounting_entry_id')) {
                    try {
                        $table->dropConstrainedForeignId('accounting_entry_id');
                    } catch (Throwable $e) {
                        try {
                            $table->dropColumn('accounting_entry_id');
                        } catch (Throwable $ignored) {
                        }
                    }
                }

                foreach (['accounting_status', 'accounting_posted_at', 'accounting_error_message'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        try {
                            $table->dropColumn($column);
                        } catch (Throwable $e) {
                        }
                    }
                }
            });
        }
    }
};
