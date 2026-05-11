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

function contactHasColumn(string $column): bool
{
    return Schema::hasColumn('contacts', $column);
}

function setContact(array &$data, string $column, mixed $value): void
{
    if (contactHasColumn($column)) {
        $data[$column] = $value;
    }
}

function fillContactRequiredDefaults(array &$data): void
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

        if (str_contains($type, 'int') || str_contains($type, 'numeric') || str_contains($type, 'decimal')) {
            $data[$name] = 0;
        } elseif (str_contains($type, 'bool')) {
            $data[$name] = false;
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

    setContact($data, 'company_id', $companyId);
    setContact($data, 'parent_contact_id', null);
    setContact($data, 'contact_type', 'company');
    setContact($data, 'address_type', 'main');

    setContact($data, 'name', $name);
    setContact($data, 'commercial_name', $name);
    setContact($data, 'fiscal_name', $name);

    setContact($data, 'is_customer', $isCustomer);
    setContact($data, 'is_supplier', $isSupplier);
    setContact($data, 'is_active', true);

    setContact($data, 'rfc', 'XAXX010101000');
    setContact($data, 'email', strtolower(str_replace([' ', 'é', 'É'], ['.', 'e', 'E'], $name)) . '.' . ($companyId ?: 'general') . '@bexia.local');
    setContact($data, 'phone', '');
    setContact($data, 'mobile', '');
    setContact($data, 'country', 'México');
    setContact($data, 'sat_country_code', 'MEX');

    setContact($data, 'internal_notes', 'Contacto genérico creado automáticamente para operaciones internas de Bexia.');

    setContact($data, 'created_at', now());
    setContact($data, 'updated_at', now());

    fillContactRequiredDefaults($data);

    $query = DB::table('contacts')->where('name', $name);

    if (contactHasColumn('company_id')) {
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

if (contactHasColumn('company_id')) {
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

$results = [];

DB::transaction(function () use ($companyIds, &$results): void {
    foreach ($companyIds as $companyId) {
        $companyId = $companyId ? (int) $companyId : null;

        $customerId = upsertGenericContact($companyId, 'Cliente genérico', true, false);
        $supplierId = upsertGenericContact($companyId, 'Proveedor genérico', false, true);

        $results[] = [
            'company_id' => $companyId,
            'cliente_generico_id' => $customerId,
            'proveedor_generico_id' => $supplierId,
        ];

        if (
            Schema::hasTable('purchase_requests')
            && Schema::hasColumn('purchase_requests', 'supplier_id')
            && Schema::hasColumn('purchase_requests', 'supplier_name')
        ) {
            $requestQuery = DB::table('purchase_requests')
                ->whereNull('supplier_id')
                ->where(function ($query): void {
                    $query
                        ->whereNull('supplier_name')
                        ->orWhere('supplier_name', '')
                        ->orWhere('supplier_name', 'Sin proveedor sugerido');
                });

            if (Schema::hasColumn('purchase_requests', 'company_id')) {
                $companyId === null
                    ? $requestQuery->whereNull('company_id')
                    : $requestQuery->where('company_id', $companyId);
            }

            $requestQuery->update([
                'supplier_id' => $supplierId,
                'supplier_name' => 'Proveedor genérico',
                'updated_at' => now(),
            ]);
        }
    }
});

echo json_encode([
    'generic_contacts' => $results,
    'generic_contacts_count' => DB::table('contacts')
        ->whereIn('name', ['Cliente genérico', 'Proveedor genérico'])
        ->count(),
    'active_suppliers_count' => DB::table('contacts')
        ->where('is_supplier', true)
        ->where('is_active', true)
        ->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
