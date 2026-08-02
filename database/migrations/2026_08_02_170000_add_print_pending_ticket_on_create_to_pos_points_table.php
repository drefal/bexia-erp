<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * BEXIA_V582P6A_PENDING_TICKET_PRINT_SWITCH_SCHEMA
     */
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'pos_points',
                'print_pending_ticket_on_create'
            )
        ) {
            Schema::table(
                'pos_points',
                function (Blueprint $table): void {
                    $table
                        ->boolean(
                            'print_pending_ticket_on_create'
                        )
                        ->default(true);
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'pos_points',
                'print_pending_ticket_on_create'
            )
        ) {
            Schema::table(
                'pos_points',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'print_pending_ticket_on_create'
                    );
                }
            );
        }
    }
};
