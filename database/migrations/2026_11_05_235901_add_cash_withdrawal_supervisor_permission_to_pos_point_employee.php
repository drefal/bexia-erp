<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_point_employee')) {
            throw new RuntimeException(
                'No existe pos_point_employee.'
            );
        }

        if (!Schema::hasColumn(
            'pos_point_employee',
            'can_authorize_cash_withdrawal'
        )) {
            Schema::table(
                'pos_point_employee',
                function (Blueprint $table): void {
                    $table->boolean(
                        'can_authorize_cash_withdrawal'
                    )->default(false);
                }
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_point_employee')
            && Schema::hasColumn(
                'pos_point_employee',
                'can_authorize_cash_withdrawal'
            )) {
            Schema::table(
                'pos_point_employee',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'can_authorize_cash_withdrawal'
                    );
                }
            );
        }
    }
};
