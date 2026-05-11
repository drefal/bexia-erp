<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Limpia asignaciones sin empresa o con 0
        DB::statement('DELETE FROM model_has_roles WHERE company_id IS NULL OR company_id = 0');
        DB::statement('DELETE FROM model_has_permissions WHERE company_id IS NULL OR company_id = 0');

        // Fuerza NOT NULL (MySQL/MariaDB)
        DB::statement('ALTER TABLE model_has_roles MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE model_has_permissions MODIFY company_id BIGINT UNSIGNED NOT NULL');
        // role_has_permissions NO lleva company_id (se mantiene global por diseño)
    }

    public function down(): void
    {
        // Si necesitas revertir, vuelve a permitir NULL (opcional)
        DB::statement('ALTER TABLE model_has_roles MODIFY company_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE model_has_permissions MODIFY company_id BIGINT UNSIGNED NULL');
    }
};
