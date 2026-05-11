<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$companyId = (int) getenv('BEXIA_COMPANY_ID') ?: 3;
$satCode = getenv('BEXIA_SAT_CODE') ?: '44121600';
$backupFile = getenv('BEXIA_BACKUP_FILE');

if (! Schema::hasTable('products')) {
    throw new RuntimeException('No existe la tabla products.');
}

if (! Schema::hasColumn('products', 'sat_product_service_code')) {
    throw new RuntimeException('La tabla products no tiene la columna sat_product_service_code.');
}

if (! Schema::hasColumn('products', 'company_id')) {
    throw new RuntimeException('La tabla products no tiene la columna company_id.');
}

$satExists = true;

if (Schema::hasTable('sat_product_service_codes')) {
    $satExists = DB::table('sat_product_service_codes')
        ->where('code', $satCode)
        ->exists();
}

if (! $satExists) {
    throw new RuntimeException("La clave SAT {$satCode} no existe en sat_product_service_codes. Importa el catálogo primero o revisa el código.");
}

$before = DB::table('products')
    ->where('company_id', $companyId)
    ->select('sat_product_service_code', DB::raw('count(*) as total'))
    ->groupBy('sat_product_service_code')
    ->orderByDesc('total')
    ->get();

dump('ANTES');
$before->each(fn ($r) => dump((array) $r));

$products = DB::table('products')
    ->where('company_id', $companyId)
    ->select([
        'id',
        'company_id',
        'name',
        'internal_reference',
        'sat_product_service_code',
        'is_variant',
        'parent_product_id',
        'updated_at',
    ])
    ->orderBy('id')
    ->get();

if ($products->isEmpty()) {
    dump([
        'empresa' => $companyId,
        'mensaje' => 'No hay productos para actualizar.',
    ]);

    return;
}

if (! $backupFile) {
    throw new RuntimeException('No se recibió BEXIA_BACKUP_FILE.');
}

$handle = fopen(base_path($backupFile), 'w');

if (! $handle) {
    throw new RuntimeException("No se pudo crear backup: {$backupFile}");
}

foreach ($products as $product) {
    fwrite($handle, json_encode((array) $product, JSON_UNESCAPED_UNICODE) . PHP_EOL);
}

fclose($handle);

$updated = DB::table('products')
    ->where('company_id', $companyId)
    ->update([
        'sat_product_service_code' => $satCode,
        'updated_at' => now(),
    ]);

$after = DB::table('products')
    ->where('company_id', $companyId)
    ->select('sat_product_service_code', DB::raw('count(*) as total'))
    ->groupBy('sat_product_service_code')
    ->orderByDesc('total')
    ->get();

dump('DESPUÉS');
$after->each(fn ($r) => dump((array) $r));

dump([
    'empresa_actualizada' => $companyId,
    'sat_product_service_code' => $satCode,
    'productos_y_variantes_actualizados' => $updated,
    'backup' => $backupFile,
]);

DB::table('products')
    ->where('company_id', $companyId)
    ->select('id', 'name', 'internal_reference', 'sat_product_service_code', 'is_variant', 'parent_product_id')
    ->orderBy('id')
    ->limit(20)
    ->get()
    ->each(fn ($r) => dump((array) $r));
