<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_points')) {
            throw new RuntimeException('No existe pos_points.');
        }

        if (! Schema::hasColumn(
            'pos_points',
            'require_cash_withdrawal_supervisor'
        )) {
            Schema::table('pos_points', function (Blueprint $table): void {
                $table->boolean('require_cash_withdrawal_supervisor')
                    ->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(
            'pos_points',
            'require_cash_withdrawal_supervisor'
        )) {
            Schema::table('pos_points', function (Blueprint $table): void {
                $table->dropColumn('require_cash_withdrawal_supervisor');
            });
        }
    }
};
