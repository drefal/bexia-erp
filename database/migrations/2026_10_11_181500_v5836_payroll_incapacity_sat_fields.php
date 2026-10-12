<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        Schema::table('employee_incidents', function (Blueprint $table): void {
            if (! Schema::hasColumn(
                'employee_incidents',
                'sat_incapacity_type_code'
            )) {
                $table->string(
                    'sat_incapacity_type_code',
                    2
                )
                    ->nullable()
                    ->after('quantity_unit');
            }

            if (! Schema::hasColumn(
                'employee_incidents',
                'incapacity_monetary_amount'
            )) {
                $table->decimal(
                    'incapacity_monetary_amount',
                    14,
                    2
                )
                    ->nullable()
                    ->after('sat_incapacity_type_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        Schema::table('employee_incidents', function (Blueprint $table): void {
            if (Schema::hasColumn(
                'employee_incidents',
                'incapacity_monetary_amount'
            )) {
                $table->dropColumn(
                    'incapacity_monetary_amount'
                );
            }

            if (Schema::hasColumn(
                'employee_incidents',
                'sat_incapacity_type_code'
            )) {
                $table->dropColumn(
                    'sat_incapacity_type_code'
                );
            }
        });
    }
};
