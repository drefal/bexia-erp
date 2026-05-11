<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (! Schema::hasTable('contacts')) {
    throw new RuntimeException('No existe la tabla contacts.');
}

function c_has(string $column): bool
{
    return Schema::hasColumn('contacts', $column);
}

function c_set(array &$data, string $column, mixed $value): void
{
    if (c_has($column)) {
        $data[$column] = $value;
    }
}

function c_required_defaults(array &$data): void
{
    $columns = DB::select("
        select column_name, data_type, is_nullable, column_default
        from information_schema.columns
        where table_name = 'contacts'
          and table_schema = current_schema()
        order by ordinal_position
    ");

    foreach ($columns as $column) {
        $name = $column->column_name;

        if (array_key_exists($name, $data)) {
            continue;
        }

        if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
            continue;
        }

        if ($column->is_nullable === 'YES' || $column->column_default !== null) {
            continue;
        }

        $type = strtolower((string) $column->data_type);

        if (str_contains($type, 'bool')) {
            $data[$name] = false;
        } elseif (
            str_contains($type, 'int')
            || str_contains($type, 'numeric')
            || str_contains($type, 'decimal')
            || str_contains($type, 'double')
            || str_contains($type, 'real')
        ) {
            $data[$name] = 0;
        } elseif (str_contains($type, 'timestamp') || str_contains($type, 'date')) {
            $data[$name] = now();
        } else {
            $data[$name] = '';
        }
    }
}

function upsert_generic_contact(?int $companyId, string $name, bool $isCustomer, bool $isSupplier, string $rfc): int
{
    $data = [];

    c_set($data, 'company_id', $companyId);
    c_set($data, 'parent_contact_id', null);
    c_set($data, 'contact_type', 'company');
    c_set($data, 'address_type', 'main');

    c_set($data, 'name', $name);
    c_set($data, 'commercial_name', $name);
    c_set($data, 'fiscal_name', $name);

    c_set($data, 'is_customer', $isCustomer);
    c_set($data, 'is_supplier', $isSupplier);
    c_set($data, 'is_active', true);

    c_set($data, 'rfc', $rfc);
    c_set($data, 'email', strtolower(str_replace([' ', 'é', 'É'], ['.', 'e', 'e'], $name)) . '.' . ($companyId ?: 'general') . '@bexia.local');

    c_set($data, 'phone', '');
    c_set($data, 'mobile', '');
    c_set($data, 'country', 'México');
    c_set($data, 'sat_country_code', 'MEX');
    c_set($data, 'fiscal_zip', '00000');
    c_set($data, 'postal_code', '00000');
    c_set($data, 'internal_notes', 'Contacto genérico creado automáticamente para operaciones internas de Bexia.');

    c_set($data, 'created_at', now());
    c_set($data, 'updated_at', now());

    c_required_defaults($data);

    $query = DB::table('contacts')->where('name', $name);

    if (c_has('company_id')) {
        $companyId === null
            ? $query->whereNull('company_id')
            : $query->where('company_id', $companyId);
    }

    $existing = $query->first();

    if ($existing) {
        $update = $data;
        unset($update['created_at']);

        DB::table('contacts')->where('id', $existing->id)->update($update);

        return (int) $existing->id;
    }

    return (int) DB::table('contacts')->insertGetId($data);
}

$companyIds = collect();

if (Schema::hasTable('companies')) {
    $companyIds = $companyIds->merge(DB::table('companies')->pluck('id'));
}

if (c_has('company_id')) {
    $companyIds = $companyIds->merge(
        DB::table('contacts')
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id')
    );
}

$companyIds = $companyIds
    ->filter(fn ($id) => $id !== null && $id !== '')
    ->map(fn ($id) => (int) $id)
    ->unique()
    ->values();

if ($companyIds->isEmpty()) {
    $companyIds = collect([null]);
}

$result = [];

foreach ($companyIds as $companyId) {
    $companyId = $companyId ? (int) $companyId : null;

    DB::beginTransaction();

    try {
        $clienteId = upsert_generic_contact(
            $companyId,
            'Cliente genérico',
            true,
            false,
            'XAXX010101000'
        );

        $proveedorId = upsert_generic_contact(
            $companyId,
            'Proveedor genérico',
            false,
            true,
            'XEXX010101000'
        );

        DB::commit();

        $result[] = [
            'company_id' => $companyId,
            'cliente_generico_id' => $clienteId,
            'proveedor_generico_id' => $proveedorId,
            'ok' => true,
        ];
    } catch (Throwable $e) {
        DB::rollBack();

        $result[] = [
            'company_id' => $companyId,
            'ok' => false,
            'error' => $e->getMessage(),
        ];
    }
}

$genericos = DB::table('contacts')
    ->whereIn('name', ['Cliente genérico', 'Proveedor genérico'])
    ->orderBy('company_id')
    ->orderBy('name')
    ->get([
        'id',
        'company_id',
        'name',
        'commercial_name',
        'rfc',
        'is_customer',
        'is_supplier',
        'is_active',
        'contact_type',
    ]);

echo json_encode([
    'empresas_detectadas' => $companyIds,
    'resultado' => $result,
    'genericos' => $genericos,
    'conteo_cliente_generico' => DB::table('contacts')->where('name', 'Cliente genérico')->count(),
    'conteo_proveedor_generico' => DB::table('contacts')->where('name', 'Proveedor genérico')->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
