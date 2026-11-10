<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('petty_cash_funds')
            && ! Schema::hasColumn('petty_cash_funds', 'location')
        ) {
            Schema::table(
                'petty_cash_funds',
                function (Blueprint $table): void {
                    $table->string('location', 255)->nullable();
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('petty_cash_funds')
            && Schema::hasColumn('petty_cash_funds', 'location')
        ) {
            Schema::table(
                'petty_cash_funds',
                function (Blueprint $table): void {
                    $table->dropColumn('location');
                }
            );
        }
    }
};
