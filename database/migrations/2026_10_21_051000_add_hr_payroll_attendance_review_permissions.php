<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'rrhh.prenomina.revisiones.ver',
            'rrhh.prenomina.visto_bueno',
            'rrhh.prenomina.revisiones.gestionar',
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Deliberadamente no borrar permisos.
        // Una migracion inversa no debe eliminar asignaciones
        // que pudieran crearse posteriormente.
    }
};
