<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $company = 3;

            $ensureAttribute = function (
                string $code,
                string $name
            ) use ($company): int {
                $rows = DB::table('product_attributes')
                    ->where('company_id', $company)
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->get();

                if ($rows->count() > 1) {
                    throw new RuntimeException(
                        "Atributo duplicado: {$code}"
                    );
                }

                if ($rows->count() === 1) {
                    $row = $rows->first();

                    if (
                        $row->name !== $name ||
                        ! $row->is_active ||
                        ! $row->is_variant
                    ) {
                        throw new RuntimeException(
                            "Atributo incompatible: {$code}"
                        );
                    }

                    return (int) $row->id;
                }

                return (int) DB::table('product_attributes')
                    ->insertGetId([
                        'company_id' => $company,
                        'code' => $code,
                        'name' => $name,
                        'is_variant' => true,
                        'is_active' => true,
                        'is_system' => false,
                        'sort_order' => 500,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            };

            $ensureValue = function (
                int $attributeId,
                string $code,
                string $name
            ) use ($company): void {
                $rows = DB::table('product_attribute_values')
                    ->where('company_id', $company)
                    ->where('product_attribute_id', $attributeId)
                    ->where(function ($q) use ($code, $name) {
                        $q->where('code', $code)
                          ->orWhereRaw(
                              'LOWER(name) = LOWER(?)',
                              [$name]
                          );
                    })
                    ->lockForUpdate()
                    ->get();

                if ($rows->count() > 1) {
                    throw new RuntimeException(
                        "Valores ambiguos: {$name}"
                    );
                }

                if ($rows->count() === 1) {
                    $value = $rows->first();

                    if (! $value->is_active) {
                        throw new RuntimeException(
                            "Valor inactivo: {$name}"
                        );
                    }

                    if (
                        strcasecmp($value->name, $name) !== 0
                    ) {
                        throw new RuntimeException(
                            "Codigo ocupado: {$code}"
                        );
                    }

                    return;
                }

                DB::table('product_attribute_values')
                    ->insert([
                        'company_id' => $company,
                        'product_attribute_id' => $attributeId,
                        'code' => $code,
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => 500,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            };

            $size = $ensureAttribute('TAMANO', 'Tamaño');
            $inflation = $ensureAttribute('INFLADO', 'Inflado');

            foreach ([
                ['GLB18PUL', '18"'],
                ['GLB20PUL', '20"'],
                ['GLB24PUL', '24"'],
            ] as [$code, $name]) {
                $ensureValue($size, $code, $name);
            }

            foreach ([
                ['NORMAL', 'Normal'],
                ['CON_HELIO', 'Con Helio'],
            ] as [$code, $name]) {
                $ensureValue($inflation, $code, $name);
            }
        });
    }

    public function down(): void
    {
        // Conservador: no eliminar valores que puedan
        // haber sido utilizados por productos o variantes.
    }
};
