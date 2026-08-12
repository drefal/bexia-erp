<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = array_values(array_filter([
            'attention_route',
            'classified_at',
            'classified_by',
            'classification_notes',
            'non_repair_type',
            'resolution_type',
            'resolution_notes',
        ], fn (string $column): bool =>
            ! Schema::hasColumn('service_cases', $column)
        ));

        if ($missing !== []) {
            Schema::table('service_cases', function (Blueprint $table) use ($missing): void {
                if (in_array('attention_route', $missing, true)) {
                    $table->string('attention_route', 30)
                        ->nullable()
                        ->index();
                }

                if (in_array('classified_at', $missing, true)) {
                    $table->timestamp('classified_at')
                        ->nullable()
                        ->index();
                }

                if (in_array('classified_by', $missing, true)) {
                    $table->unsignedBigInteger('classified_by')
                        ->nullable()
                        ->index();
                }

                if (in_array('classification_notes', $missing, true)) {
                    $table->text('classification_notes')
                        ->nullable();
                }

                if (in_array('non_repair_type', $missing, true)) {
                    $table->string('non_repair_type', 80)
                        ->nullable()
                        ->index();
                }

                if (in_array('resolution_type', $missing, true)) {
                    $table->string('resolution_type', 80)
                        ->nullable();
                }

                if (in_array('resolution_notes', $missing, true)) {
                    $table->text('resolution_notes')
                        ->nullable();
                }
            });
        }

        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'service.cases.classify')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'service.cases.classify',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('company_id', [1, 11, 14, 15, 17])
            ->whereIn('name', [
                'Servicio - Encargado de Técnicos',
                'Servicio - Supervisor',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('permissions')
            && Schema::hasTable('role_has_permissions')
        ) {
            $permissionId = DB::table('permissions')
                ->where('name', 'service.cases.classify')
                ->where('guard_name', 'web')
                ->value('id');

            if ($permissionId) {
                DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->delete();

                DB::table('permissions')
                    ->where('id', $permissionId)
                    ->delete();
            }
        }

        $columns = array_values(array_filter([
            'attention_route',
            'classified_at',
            'classified_by',
            'classification_notes',
            'non_repair_type',
            'resolution_type',
            'resolution_notes',
        ], fn (string $column): bool =>
            Schema::hasColumn('service_cases', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('service_cases', function (Blueprint $table) use ($columns): void {
            foreach ([
                'attention_route',
                'classified_at',
                'classified_by',
                'non_repair_type',
            ] as $indexedColumn) {
                if (in_array($indexedColumn, $columns, true)) {
                    $table->dropIndex([$indexedColumn]);
                }
            }

            $table->dropColumn($columns);
        });
    }
};
