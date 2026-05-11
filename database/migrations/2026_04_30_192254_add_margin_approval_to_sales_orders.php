<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_orders', 'margin_approval_required')) {
                $table->boolean('margin_approval_required')->default(false)->index();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_approval_status')) {
                $table->string('margin_approval_status')->default('not_required')->index();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_approval_user_id')) {
                $table->unsignedBigInteger('margin_approval_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_approval_reason')) {
                $table->text('margin_approval_reason')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_approval_requested_at')) {
                $table->timestamp('margin_approval_requested_at')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_approved_by_user_id')) {
                $table->unsignedBigInteger('margin_approved_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_approved_at')) {
                $table->timestamp('margin_approved_at')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_rejected_by_user_id')) {
                $table->unsignedBigInteger('margin_rejected_by_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_rejected_at')) {
                $table->timestamp('margin_rejected_at')->nullable();
            }

            if (! Schema::hasColumn('sales_orders', 'margin_rejection_reason')) {
                $table->text('margin_rejection_reason')->nullable();
            }
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['name' => 'sales.approve_margin', 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );

            $permissionId = DB::table('permissions')
                ->where('name', 'sales.approve_margin')
                ->where('guard_name', 'web')
                ->value('id');

            if ($permissionId && Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
                DB::table('roles')
                    ->whereIn('name', ['admin', 'Admin Empresa', 'Admin Grupo', 'Administrador'])
                    ->pluck('id')
                    ->each(function ($roleId) use ($permissionId) {
                        DB::table('role_has_permissions')->insertOrIgnore([
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ]);
                    });
            }
        }
    }

    public function down(): void
    {
        //
    }
};
