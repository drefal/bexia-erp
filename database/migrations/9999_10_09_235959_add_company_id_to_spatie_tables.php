<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        /**
         * 0) Limpieza por si quedaron temporales de intentos previos
         */
        Schema::dropIfExists('model_has_permissions_tmp');
        Schema::dropIfExists('model_has_roles_tmp');

        /**
         * 1) ROLES: agregar company_id y permitir duplicar name/guard por compañía
         *    - Deja company_id como NULLABLE (permite roles “globales”)
         *    - Crea unique (name, guard_name, company_id)
         */
        Schema::table('roles', function (Blueprint $t) {
            if (!Schema::hasColumn('roles', 'company_id')) {
                $t->unsignedBigInteger('company_id')->nullable()->after('guard_name')->index();
            }
        });

        // Borra el unique antiguo si existe
        $rolesIdx = collect(DB::select("SHOW INDEX FROM roles"))->pluck('Key_name')->unique();
        if ($rolesIdx->contains('roles_name_guard_name_unique')) {
            Schema::table('roles', function (Blueprint $t) {
                $t->dropUnique('roles_name_guard_name_unique');
            });
        }
        if ($rolesIdx->contains('roles_name_guard_company_unique')) {
            Schema::table('roles', function (Blueprint $t) {
                $t->dropUnique('roles_name_guard_company_unique');
            });
        }

        // Crea el unique nuevo (name, guard_name, company_id)
        Schema::table('roles', function (Blueprint $t) {
            $t->unique(['name', 'guard_name', 'company_id'], 'roles_name_guard_company_unique');
        });

        /**
         * 2) MODEL_HAS_PERMISSIONS
         *    - Creamos tabla temporal con company_id NOT NULL DEFAULT 0 (0 = global)
         *    - Copiamos los datos
         *    - Reemplazamos la tabla
         */
        if (!Schema::hasColumn('model_has_permissions', 'company_id')) {
            Schema::create('model_has_permissions_tmp', function (Blueprint $t) {
                $t->unsignedBigInteger('permission_id');
                $t->string('model_type');
                $t->unsignedBigInteger('model_id');
                $t->unsignedBigInteger('company_id')->default(0)->index(); // NOT NULL por PK
                // Índices y FK
                $t->index(['model_id','model_type'], 'mhp_model_id_model_type_idx');
                $t->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                // PK nueva
                $t->primary(['permission_id','model_id','model_type','company_id'], 'mhp_primary');
            });

            DB::statement('
                INSERT INTO model_has_permissions_tmp (permission_id, model_type, model_id, company_id)
                SELECT permission_id, model_type, model_id, 0 FROM model_has_permissions
            ');

            Schema::drop('model_has_permissions');
            Schema::rename('model_has_permissions_tmp', 'model_has_permissions');
        }

        /**
         * 3) MODEL_HAS_ROLES
         *    - Mismo procedimiento que arriba
         */
        if (!Schema::hasColumn('model_has_roles', 'company_id')) {
            Schema::create('model_has_roles_tmp', function (Blueprint $t) {
                $t->unsignedBigInteger('role_id');
                $t->string('model_type');
                $t->unsignedBigInteger('model_id');
                $t->unsignedBigInteger('company_id')->default(0)->index(); // NOT NULL por PK
                // Índices y FK
                $t->index(['model_id','model_type'], 'mhr_model_id_model_type_idx');
                $t->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                // PK nueva
                $t->primary(['role_id','model_id','model_type','company_id'], 'mhr_primary');
            });

            DB::statement('
                INSERT INTO model_has_roles_tmp (role_id, model_type, model_id, company_id)
                SELECT role_id, model_type, model_id, 0 FROM model_has_roles
            ');

            Schema::drop('model_has_roles');
            Schema::rename('model_has_roles_tmp', 'model_has_roles');
        }
    }

    public function down(): void
    {
        /**
         * Revertir MODEL_HAS_ROLES a la forma original de Spatie (sin company_id)
         */
        if (Schema::hasTable('model_has_roles') && Schema::hasColumn('model_has_roles','company_id')) {
            Schema::create('model_has_roles_old', function (Blueprint $t) {
                $t->unsignedBigInteger('role_id');
                $t->string('model_type');
                $t->unsignedBigInteger('model_id');
                $t->index(['model_id','model_type'], 'model_has_roles_model_id_model_type_index');
                $t->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $t->primary(['role_id','model_id','model_type']);
            });

            DB::statement('
                INSERT INTO model_has_roles_old (role_id, model_type, model_id)
                SELECT role_id, model_type, model_id FROM model_has_roles
            ');

            Schema::drop('model_has_roles');
            Schema::rename('model_has_roles_old', 'model_has_roles');
        }

        /**
         * Revertir MODEL_HAS_PERMISSIONS a la forma original (sin company_id)
         */
        if (Schema::hasTable('model_has_permissions') && Schema::hasColumn('model_has_permissions','company_id')) {
            Schema::create('model_has_permissions_old', function (Blueprint $t) {
                $t->unsignedBigInteger('permission_id');
                $t->string('model_type');
                $t->unsignedBigInteger('model_id');
                $t->index(['model_id','model_type'], 'model_has_permissions_model_id_model_type_index');
                $t->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $t->primary(['permission_id','model_id','model_type']);
            });

            DB::statement('
                INSERT INTO model_has_permissions_old (permission_id, model_type, model_id)
                SELECT permission_id, model_type, model_id FROM model_has_permissions
            ');

            Schema::drop('model_has_permissions');
            Schema::rename('model_has_permissions_old', 'model_has_permissions');
        }

        /**
         * ROLES: quitar unique nuevo y columna company_id; restaurar unique original
         */
        $rolesIdx = collect(DB::select("SHOW INDEX FROM roles"))->pluck('Key_name')->unique();
        if ($rolesIdx->contains('roles_name_guard_company_unique')) {
            Schema::table('roles', function (Blueprint $t) {
                $t->dropUnique('roles_name_guard_company_unique');
            });
        }

        Schema::table('roles', function (Blueprint $t) {
            try { $t->dropColumn('company_id'); } catch (\Throwable $e) {}
            // Unique original (name, guard_name)
            $t->unique(['name','guard_name']);
        });
    }
};
