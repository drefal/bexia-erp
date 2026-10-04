<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('computer_rental_rates')
            && ! Schema::hasColumn(
                'computer_rental_rates',
                'cancellation_grace_minutes'
            )
        ) {
            Schema::table(
                'computer_rental_rates',
                function (Blueprint $table): void {
                    $table->unsignedInteger(
                        'cancellation_grace_minutes'
                    )
                        ->default(5)
                        ->after('billing_increment_minutes');
                }
            );
        }

        if (
            Schema::hasTable('computer_rental_sessions')
            && ! Schema::hasColumn(
                'computer_rental_sessions',
                'cancellation_grace_minutes'
            )
        ) {
            Schema::table(
                'computer_rental_sessions',
                function (Blueprint $table): void {
                    $table->unsignedInteger(
                        'cancellation_grace_minutes'
                    )
                        ->default(5)
                        ->after('billing_increment_minutes');
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('computer_rental_sessions')
            && Schema::hasColumn(
                'computer_rental_sessions',
                'cancellation_grace_minutes'
            )
        ) {
            Schema::table(
                'computer_rental_sessions',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'cancellation_grace_minutes'
                    );
                }
            );
        }

        if (
            Schema::hasTable('computer_rental_rates')
            && Schema::hasColumn(
                'computer_rental_rates',
                'cancellation_grace_minutes'
            )
        ) {
            Schema::table(
                'computer_rental_rates',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'cancellation_grace_minutes'
                    );
                }
            );
        }
    }
};
