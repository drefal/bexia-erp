<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('expense_projects')->insertOrIgnore([
            [
                'company_id' => 1,
                'code' => 'TIENDAS',
                'name' => 'Tiendas',
                'description' => 'Gastos relacionados con operación y actividades de tiendas.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'ENTRADA_C2',
                'name' => 'Entrada C2',
                'description' => 'Gastos relacionados con el proyecto Entrada C2.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'TIENDA_EJE_CENTRAL',
                'name' => 'Tienda Eje Central',
                'description' => 'Gastos relacionados con la Tienda Eje Central.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('funding_sources')->insertOrIgnore([
            [
                'company_id' => 1,
                'code' => 'FONDEO_INICIAL',
                'name' => 'Fondeo inicial',
                'description' => 'Asignación inicial de efectivo o recursos a una caja chica.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'REPOSICION',
                'name' => 'Reposición de caja chica',
                'description' => 'Reposición ordinaria de recursos consumidos por gastos comprobados.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'TRANSFERENCIA_INTERNA',
                'name' => 'Transferencia interna',
                'description' => 'Recursos provenientes de otra caja, cuenta o área de la misma empresa.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'REINTEGRO',
                'name' => 'Reintegro',
                'description' => 'Dinero que regresa a la caja por un reintegro previamente identificado.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'DEVOLUCION_ANTICIPO',
                'name' => 'Devolución de anticipo',
                'description' => 'Recursos devueltos correspondientes a un anticipo no utilizado o utilizado parcialmente.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'RECUPERACION_GASTO',
                'name' => 'Recuperación de gasto',
                'description' => 'Recuperación de un gasto previamente realizado por la empresa.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'APORTACION',
                'name' => 'Aportación',
                'description' => 'Ingreso extraordinario o aportación autorizada para la operación.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'COBRO',
                'name' => 'Cobro / recuperación',
                'description' => 'Recursos recibidos por cobro o recuperación autorizada.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'OTROS',
                'name' => 'Otros ingresos',
                'description' => 'Otros orígenes de recursos no clasificados. Debe explicarse en notas.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'CORTES_BICIKRON',
                'name' => 'Cortes Bicikron',
                'description' => 'Recursos provenientes de cortes de Bicikron.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'CORTES_MONK',
                'name' => 'Cortes Monk',
                'description' => 'Recursos provenientes de cortes de Monk.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'CORTES_MUVITT',
                'name' => 'Cortes Muvitt',
                'description' => 'Recursos provenientes de cortes de Muvitt.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'CORTES_BTOYS',
                'name' => 'Cortes Btoys',
                'description' => 'Recursos provenientes de cortes de Btoys.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'TIENDAS_FORANEAS',
                'name' => 'Tiendas Foráneas',
                'description' => 'Recursos provenientes de tiendas foráneas.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => 1,
                'code' => 'CLIENTES',
                'name' => 'Clientes',
                'description' => 'Recursos provenientes de clientes.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        /*
         * No se eliminan catálogos en rollback porque pueden
         * haber sido utilizados por comprobaciones o movimientos.
         */
    }
};
