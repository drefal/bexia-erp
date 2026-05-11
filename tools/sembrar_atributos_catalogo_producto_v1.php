<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function bxNow(): string
{
    return now()->toDateTimeString();
}

function bxUpsert(string $table, array $keys, array $values): void
{
    $query = DB::table($table);

    foreach ($keys as $column => $value) {
        $query->where($column, $value);
    }

    if ($query->exists()) {
        DB::table($table)->where($keys)->update(array_merge($values, [
            'updated_at' => bxNow(),
        ]));

        return;
    }

    DB::table($table)->insert(array_merge($keys, $values, [
        'created_at' => bxNow(),
        'updated_at' => bxNow(),
    ]));
}

foreach ([
    'companies',
    'product_attributes',
    'product_attribute_values',
] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

$attributes = [
    ['code' => 'MARCA', 'name' => 'Marca', 'is_variant' => false],
    ['code' => 'MODELO', 'name' => 'Modelo', 'is_variant' => false],
    ['code' => 'MATERIAL', 'name' => 'Material', 'is_variant' => false],
    ['code' => 'COLOR', 'name' => 'Color', 'is_variant' => true],
    ['code' => 'TALLA', 'name' => 'Talla', 'is_variant' => true],
    ['code' => 'LINEA', 'name' => 'Línea', 'is_variant' => false],
    ['code' => 'CONDICION', 'name' => 'Condición', 'is_variant' => false],
    ['code' => 'CATALOGO', 'name' => 'Catálogo', 'is_variant' => false],
];

$baseValues = [
    'CONDICION' => [
        ['code' => 'NUEVO', 'name' => 'Nuevo'],
        ['code' => 'USADO', 'name' => 'Usado'],
        ['code' => 'REACONDICIONADO', 'name' => 'Reacondicionado'],
        ['code' => 'NO_APLICA', 'name' => 'No aplica'],
    ],
    'CATALOGO' => [
        ['code' => 'LINEA', 'name' => 'Línea'],
        ['code' => 'TEMPORADA', 'name' => 'Temporada'],
        ['code' => 'PROMOCIONAL', 'name' => 'Promocional'],
    ],
];

$companies = DB::table('companies')->select('id', 'name')->orderBy('id')->get();

DB::transaction(function () use ($companies, $attributes, $baseValues): void {
    foreach ($companies as $company) {
        echo PHP_EOL . "Empresa {$company->id}: {$company->name}" . PHP_EOL;

        foreach ($attributes as $index => $attribute) {
            bxUpsert('product_attributes', [
                'company_id' => $company->id,
                'code' => $attribute['code'],
            ], [
                'name' => $attribute['name'],
                'is_variant' => $attribute['is_variant'],
                'is_active' => true,
                'is_system' => true,
                'sort_order' => $index + 1,
            ]);

            $attributeId = DB::table('product_attributes')
                ->where('company_id', $company->id)
                ->where('code', $attribute['code'])
                ->value('id');

            echo "Atributo {$attribute['code']} {$attribute['name']}" . PHP_EOL;

            foreach ($baseValues[$attribute['code']] ?? [] as $valueIndex => $value) {
                bxUpsert('product_attribute_values', [
                    'product_attribute_id' => $attributeId,
                    'code' => $value['code'],
                ], [
                    'company_id' => $company->id,
                    'name' => $value['name'],
                    'is_active' => true,
                    'sort_order' => $valueIndex + 1,
                ]);

                echo "- Valor {$value['code']} {$value['name']}" . PHP_EOL;
            }
        }
    }
});
