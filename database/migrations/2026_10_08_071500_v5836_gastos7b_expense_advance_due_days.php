<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('expense_advances')
            && ! Schema::hasColumn(
                'expense_advances',
                'due_days'
            )
        ) {
            Schema::table(
                'expense_advances',
                function (Blueprint $table): void {
                    $table->unsignedInteger('due_days')
                        ->default(5)
                        ->after('due_date');
                }
            );
        }

        /*
         * Conservamos el plazo histórico de anticipos creados
         * antes de esta columna.
         */
        DB::statement("
            UPDATE expense_advances
            SET due_days = GREATEST(
                (due_date - request_date),
                0
            )
            WHERE due_date IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (
            Schema::hasTable('expense_advances')
            && Schema::hasColumn(
                'expense_advances',
                'due_days'
            )
        ) {
            Schema::table(
                'expense_advances',
                function (Blueprint $table): void {
                    $table->dropColumn('due_days');
                }
            );
        }
    }
};
