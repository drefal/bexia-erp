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

function hasContactColumn(string $column): bool
{
    return Schema::hasColumn('contacts', $column);
}

function setContactColumn(array &$data, string $column, mixed $value): void
{
    if (hasContactColumn($column)) {
        $data[$column] = $value;
    }
}

function fillRequiredContactColumns(array &$data): void
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

function upsertGenericContact(?int $companyId, string $name, bool $isCustomer, bool $isSupplier): int
{
    $data = [];

    setContactColumn($data, 'company_id', $companyId);
    setContactColumn($data, 'parent_contact_id', null);
    setContactColumn($data, 'contact_type', 'company');
    setContactColumn($data, 'address_type', 'main');

    setContactColumn($data, 'name', $name);
    setContactColumn($data, 'commercial_name', $name);
    setContactColumn($data, 'fiscal_name', $name);

    setContactColumn($data, 'is_customer', $isCustomer);
    setContactColumn($data, 'is_supplier', $isSupplier);
    setContactColumn($data, 'is_active', true);

    setContactColumn($data, 'rfc', 'XAXX010101000');
    setContactColumn($data, 'email', strtolower(str_replace([' ', 'é', 'É'], ['.', 'e', 'e'], $name)) . '.' . ($companyId ?: 'general') . '@bexia.local');
    setContactColumn($data, 'phone', '');
    setContactColumn($data, 'mobile', '');

    setContactColumn($data, 'country', 'México');
    setContactColumn($data, 'sat_country_code', 'MEX');
    setContactColumn($data, 'internal_notes', 'Contacto genérico creado automáticamente para operaciones internas de Bexia.');

    setContactColumn($data, 'created_at', now());
    setContactColumn($data, 'updated_at', now());

    fillRequiredContactColumns($data);

    $query = DB::table('contacts')->where('name', $name);

    if (hasContactColumn('company_id')) {
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
    $companyIds = $companyIds->merge(
        DB::table('companies')
            ->orderBy('id')
            ->pluck('id')
    );
}

if (hasContactColumn('company_id')) {
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

DB::transaction(function () use ($companyIds, &$result): void {
    foreach ($companyIds as $companyId) {
        $companyId = $companyId ? (int) $companyId : null;

        $clienteId = upsertGenericContact($companyId, 'Cliente genérico', true, false);
        $proveedorId = upsertGenericContact($companyId, 'Proveedor genérico', false, true);

        $result[] = [
            'company_id' => $companyId,
            'cliente_generico_id' => $clienteId,
            'proveedor_generico_id' => $proveedorId,
        ];
    }
});

$genericos = DB::table('contacts')
    ->whereIn('name', ['Cliente genérico', 'Proveedor genérico'])
    ->orderBy('company_id')
    ->orderBy('name')
    ->get([
        'id',
        'company_id',
        'name',
        'commercial_name',
        'is_customer',
        'is_supplier',
        'is_active',
        'contact_type',
    ]);

echo json_encode([
    'empresas_procesadas' => $companyIds,
    'resultado' => $result,
    'genericos' => $genericos,
    'conteo_cliente_generico' => DB::table('contacts')->where('name', 'Cliente genérico')->count(),
    'conteo_proveedor_generico' => DB::table('contacts')->where('name', 'Proveedor genérico')->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
