<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'is_service_technician')) {
                    $table->boolean('is_service_technician')->default(false)->index();
                }
            });
        }

        if (Schema::hasTable('service_cases')) {
            Schema::table('service_cases', function (Blueprint $table) {
                if (! Schema::hasColumn('service_cases', 'assigned_employee_id')) {
                    $table->unsignedBigInteger('assigned_employee_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('repair_orders', 'assigned_employee_id')) {
                    $table->unsignedBigInteger('assigned_employee_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_orders') && Schema::hasColumn('repair_orders', 'assigned_employee_id')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                $table->dropColumn('assigned_employee_id');
            });
        }

        if (Schema::hasTable('service_cases') && Schema::hasColumn('service_cases', 'assigned_employee_id')) {
            Schema::table('service_cases', function (Blueprint $table) {
                $table->dropColumn('assigned_employee_id');
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'is_service_technician')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('is_service_technician');
            });
        }
    }
};
