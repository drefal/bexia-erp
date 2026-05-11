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

if (! Schema::hasTable('sat_unit_codes')) {
    throw new RuntimeException('Falta tabla sat_unit_codes');
}

if (! Schema::hasColumn('sat_unit_codes', 'type')) {
    throw new RuntimeException('Falta columna type en sat_unit_codes');
}

$units = [
    ['type' => 'Múltiplos / Fracciones / Decimales', 'code' => 'H87', 'name' => 'Pieza'],
    ['type' => 'Unidades de venta', 'code' => 'EA', 'name' => 'Elemento'],
    ['type' => 'Unidades específicas de la industria (varias)', 'code' => 'E48', 'name' => 'Unidad de servicio'],
    ['type' => 'Unidades de venta', 'code' => 'ACT', 'name' => 'Actividad'],
    ['type' => 'Mecánica', 'code' => 'KGM', 'name' => 'Kilogramo'],
    ['type' => 'Unidades específicas de la industria (varias)', 'code' => 'E51', 'name' => 'Trabajo'],
    ['type' => 'Diversos', 'code' => 'A9', 'name' => 'Tarifa'],
    ['type' => 'Tiempo y Espacio', 'code' => 'MTR', 'name' => 'Metro'],
    ['type' => 'Diversos', 'code' => 'AB', 'name' => 'Paquete a granel'],
    ['type' => 'Unidades especificas de la industria (varias)', 'code' => 'BB', 'name' => 'Caja base'],
    ['type' => 'Unidades de venta', 'code' => 'KT', 'name' => 'KIT'],
    ['type' => 'Unidades de venta', 'code' => 'SET', 'name' => 'Conjunto'],
    ['type' => 'Tiempo y Espacio', 'code' => 'LTR', 'name' => 'Litro'],
    ['type' => 'Unidades de empaque', 'code' => 'XBX', 'name' => 'Caja'],
    ['type' => 'Tiempo y Espacio', 'code' => 'MON', 'name' => 'Mes'],
    ['type' => 'Tiempo y Espacio', 'code' => 'HUR', 'name' => 'Hora'],
    ['type' => 'Tiempo y Espacio', 'code' => 'MTK', 'name' => 'Metro Cuadrado'],
    ['type' => 'Diversos', 'code' => '11', 'name' => 'Equipos'],
    ['type' => 'Mecánica', 'code' => 'MGM', 'name' => 'Miligramo'],
    ['type' => 'Unidades de empaque', 'code' => 'XPK', 'name' => 'Paquete'],
    ['type' => 'Unidades de empaque', 'code' => 'XKI', 'name' => 'Kit (Conjunto de piezas)'],
    ['type' => 'Diversos', 'code' => 'AS', 'name' => 'Variedad'],
    ['type' => 'Mecánica', 'code' => 'GRM', 'name' => 'Gramo'],
    ['type' => 'Números en enteros / Números / Ratios', 'code' => 'PR', 'name' => 'Par'],
    ['type' => 'Unidades de venta', 'code' => 'DPC', 'name' => 'Docenas de piezas'],
    ['type' => 'Unidades de empaque', 'code' => 'XUN', 'name' => 'Unidad'],
    ['type' => 'Tiempo y Espacio', 'code' => 'DAY', 'name' => 'Día'],
    ['type' => 'Unidades de empaque', 'code' => 'XLT', 'name' => 'Lote'],
    ['type' => 'Diversos', 'code' => '10', 'name' => 'Grupos'],
    ['type' => 'Tiempo y Espacio', 'code' => 'MLT', 'name' => 'Mililitro'],
    ['type' => 'Unidades específicas de la industria (varias)', 'code' => 'E54', 'name' => 'Viaje'],
];

DB::transaction(function () use ($units): void {
    foreach ($units as $unit) {
        bxUpsert('sat_unit_codes', [
            'code' => $unit['code'],
        ], [
            'type' => $unit['type'],
            'name' => $unit['name'],
            'symbol' => null,
            'is_active' => true,
        ]);

        echo "SAT Unidad {$unit['code']} {$unit['name']} ({$unit['type']})" . PHP_EOL;
    }
});

echo PHP_EOL . 'Total sat_unit_codes: ' . DB::table('sat_unit_codes')->count() . PHP_EOL;
