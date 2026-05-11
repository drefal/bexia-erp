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
                if (! Schema::hasColumn('employees', 'is_pos_cashier')) {
                    $table->boolean('is_pos_cashier')->default(false)->index();
                }

                if (! Schema::hasColumn('employees', 'is_pos_seller')) {
                    $table->boolean('is_pos_seller')->default(false)->index();
                }

                if (! Schema::hasColumn('employees', 'pos_active')) {
                    $table->boolean('pos_active')->default(true)->index();
                }

                if (! Schema::hasColumn('employees', 'pos_pin_hash')) {
                    $table->string('pos_pin_hash')->nullable();
                }
            });
        }

        if (! Schema::hasTable('pos_point_employee')) {
            Schema::create('pos_point_employee', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('pos_point_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('role')->default('cashier')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('can_create_ticket')->default(true);
                $table->boolean('can_charge')->default(true);
                $table->boolean('can_discount')->default(true);
                $table->boolean('can_cancel')->default(true);
                $table->boolean('can_open_cash_drawer')->default(false);
                $table->decimal('max_discount_percent', 8, 2)->default(0);
                $table->timestamps();

                $table->unique(['pos_point_id', 'employee_id', 'role'], 'pos_point_employee_unique');
            });
        }

        if (Schema::hasTable('pos_points')) {
            Schema::table('pos_points', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_points', 'box_type')) {
                    $table->string('box_type')->default('mixed')->index();
                }
            });
        }

        if (Schema::hasTable('pos_sessions')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_sessions', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->index();
                }

                if (! Schema::hasColumn('pos_sessions', 'staff_role')) {
                    $table->string('staff_role')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // No se elimina para evitar perdida de configuracion.
    }
};
