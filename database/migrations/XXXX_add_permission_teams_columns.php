<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableNames  = config('permission.table_names');
        $columnNames = config('permission.column_names'); // aquí viene 'team_foreign_key' => 'company_id'

        // roles
        Schema::table($tableNames['roles'], function (Blueprint $table) use ($columnNames) {
            if (! Schema::hasColumn($table->getTable(), $columnNames['team_foreign_key'])) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable()->after('id');
                $table->unique(['name','guard_name', $columnNames['team_foreign_key']], 'roles_name_guard_team_unique');
            }
        });

        // model_has_permissions
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
            if (! Schema::hasColumn($table->getTable(), $columnNames['team_foreign_key'])) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index([$columnNames['team_foreign_key'], 'model_id', 'model_type'], 'model_has_permissions_team_model_type_index');
                $table->dropPrimary();
                $table->primary(['permission_id', $columnNames['team_foreign_key'], 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });

        // model_has_roles
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
            if (! Schema::hasColumn($table->getTable(), $columnNames['team_foreign_key'])) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index([$columnNames['team_foreign_key'], 'model_id', 'model_type'], 'model_has_roles_team_model_type_index');
                $table->dropPrimary();
                $table->primary(['role_id', $columnNames['team_foreign_key'], 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            }
        });

        app('cache')->store(
            config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null
        )->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames  = config('permission.table_names');
        $columnNames = config('permission.column_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($columnNames) {
            if (Schema::hasColumn($table->getTable(), $columnNames['team_foreign_key'])) {
                $table->dropUnique('roles_name_guard_team_unique');
                $table->dropColumn($columnNames['team_foreign_key']);
            }
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
            if (Schema::hasColumn($table->getTable(), $columnNames['team_foreign_key'])) {
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
                $table->dropIndex('model_has_permissions_team_model_type_index');
                $table->dropColumn($columnNames['team_foreign_key']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            }
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
            if (Schema::hasColumn($table->getTable(), $columnNames['team_foreign_key'])) {
                $table->dropPrimary('model_has_roles_role_model_type_primary');
                $table->dropIndex('model_has_roles_team_model_type_index');
                $table->dropColumn($columnNames['team_foreign_key']);
                $table->primary(['role_id', 'model_id', 'model_type']);
            }
        });
    }
};
