<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$resourcePath = base_path('app/Filament/Resources/PurchaseRequestResource.php');

if (! file_exists($resourcePath)) {
    throw new RuntimeException('No existe PurchaseRequestResource.php');
}

if (! Schema::hasTable('contacts')) {
    throw new RuntimeException('No existe tabla contacts');
}

function contactColumnExists(string $column): bool
{
    return Schema::hasColumn('contacts', $column);
}

function setContactValue(array &$data, string $column, mixed $value): void
{
    if (contactColumnExists($column)) {
        $data[$column] = $value;
    }
}

function fillRequiredContactDefaults(array &$data): void
{
    $columns = DB::select("
        select column_name, data_type, is_nullable, column_default
        from information_schema.columns
        where table_name = 'contacts'
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

    setContactValue($data, 'company_id', $companyId);
    setContactValue($data, 'contact_type', 'company');
    setContactValue($data, 'address_type', 'main');

    setContactValue($data, 'name', $name);
    setContactValue($data, 'commercial_name', $name);
    setContactValue($data, 'fiscal_name', $name);

    setContactValue($data, 'is_customer', $isCustomer);
    setContactValue($data, 'is_supplier', $isSupplier);
    setContactValue($data, 'is_active', true);

    setContactValue($data, 'rfc', 'XAXX010101000');
    setContactValue($data, 'email', strtolower(str_replace(' ', '.', str_replace('é', 'e', $name))) . '.' . ($companyId ?: 'general') . '@bexia.local');
    setContactValue($data, 'phone', '');
    setContactValue($data, 'mobile', '');
    setContactValue($data, 'country', 'México');
    setContactValue($data, 'sat_country_code', 'MEX');
    setContactValue($data, 'internal_notes', 'Contacto genérico creado automáticamente para operaciones internas de Bexia.');

    setContactValue($data, 'created_at', now());
    setContactValue($data, 'updated_at', now());

    fillRequiredContactDefaults($data);

    $query = DB::table('contacts')->where('name', $name);

    if (contactColumnExists('company_id')) {
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
    $companyIds = DB::table('companies')->orderBy('id')->pluck('id');
}

if ($companyIds->isEmpty() && contactColumnExists('company_id')) {
    $companyIds = DB::table('contacts')->whereNotNull('company_id')->distinct()->orderBy('company_id')->pluck('company_id');
}

if ($companyIds->isEmpty()) {
    $companyIds = collect([null]);
}

$createdOrUpdated = [];

DB::transaction(function () use ($companyIds, &$createdOrUpdated): void {
    foreach ($companyIds as $companyId) {
        $companyId = $companyId ? (int) $companyId : null;

        $createdOrUpdated[] = [
            'company_id' => $companyId,
            'cliente_generico_id' => upsertGenericContact($companyId, 'Cliente genérico', true, false),
            'proveedor_generico_id' => upsertGenericContact($companyId, 'Proveedor genérico', false, true),
        ];
    }
});

$text = file_get_contents($resourcePath);

if (! str_contains($text, "Forms\\Components\\TextInput::make('supplier_name')")) {
    echo "WARN: No encontré TextInput::make('supplier_name'). Igual revisaré si ya existe supplier_id." . PHP_EOL;
}

function findComponentBlock(string $text, string $needle): ?array
{
    $start = strpos($text, $needle);

    if ($start === false) {
        return null;
    }

    $lineStart = strrpos(substr($text, 0, $start), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;

    $paren = 0;
    $bracket = 0;
    $brace = 0;
    $inSingle = false;
    $inDouble = false;
    $escape = false;
    $length = strlen($text);

    for ($i = $start; $i < $length; $i++) {
        $ch = $text[$i];

        if ($escape) {
            $escape = false;
            continue;
        }

        if ($ch === '\\') {
            $escape = true;
            continue;
        }

        if ($ch === "'" && ! $inDouble) {
            $inSingle = ! $inSingle;
            continue;
        }

        if ($ch === '"' && ! $inSingle) {
            $inDouble = ! $inDouble;
            continue;
        }

        if ($inSingle || $inDouble) {
            continue;
        }

        if ($ch === '(') {
            $paren++;
        } elseif ($ch === ')') {
            $paren--;
        } elseif ($ch === '[') {
            $bracket++;
        } elseif ($ch === ']') {
            $bracket--;
        } elseif ($ch === '{') {
            $brace++;
        } elseif ($ch === '}') {
            $brace--;
        }

        if ($ch === ',' && $paren === 0 && $bracket === 0 && $brace === 0) {
            return [$lineStart, $i + 1];
        }
    }

    return null;
}

foreach ([
    "Forms\\Components\\Select::make('supplier_id')",
    "Forms\\Components\\TextInput::make('supplier_name')",
    "Forms\\Components\\Hidden::make('supplier_name')",
] as $needle) {
    while ($block = findComponentBlock($text, $needle)) {
        [$start, $end] = $block;
        $text = substr($text, 0, $start) . substr($text, $end);
    }
}

$folioBlock = findComponentBlock($text, "Forms\\Components\\TextInput::make('number')");

if (! $folioBlock) {
    throw new RuntimeException('No encontré el campo Folio.');
}

[$folioStart, $folioEnd] = $folioBlock;

$newSupplierField = <<<'PHPRESOURCE'

                        Forms\Components\Select::make('supplier_id')
                            ->label('Proveedor')
                            ->options(fn (): array => static::supplierOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin proveedor sugerido')
                            ->reactive()
                            ->afterStateHydrated(function ($state, Forms\Set $set, ?PurchaseRequest $record): void {
                                if ($state) {
                                    $set('supplier_name', static::supplierLabel($state));
                                    return;
                                }

                                if ($record && $record->supplier_name) {
                                    $supplierId = static::supplierIdByName($record->supplier_name);

                                    if ($supplierId) {
                                        $set('supplier_id', $supplierId);
                                        $set('supplier_name', static::supplierLabel($supplierId));
                                    }
                                }
                            })
                            ->afterStateUpdated(fn ($state, Forms\Set $set): mixed => $set('supplier_name', $state ? static::supplierLabel($state) : 'Sin proveedor sugerido'))
                            ->helperText('Solo muestra contactos activos marcados como proveedor.'),

                        Forms\Components\Hidden::make('supplier_name')
                            ->dehydrated(true),
PHPRESOURCE;

$text = substr($text, 0, $folioEnd) . $newSupplierField . substr($text, $folioEnd);

$methods = <<<'PHPRESOURCE'

    protected static function supplierOptions(): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        $query = DB::table('contacts')
            ->where('is_supplier', true);

        if (Schema::hasColumn('contacts', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('contacts', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'commercial_name'])
            ->mapWithKeys(fn ($contact): array => [
                $contact->id => trim((string) ($contact->commercial_name ?: $contact->name ?: ('Proveedor #' . $contact->id))),
            ])
            ->all();
    }

    protected static function supplierLabel($supplierId): string
    {
        if (! $supplierId || ! Schema::hasTable('contacts')) {
            return 'Sin proveedor sugerido';
        }

        $contact = DB::table('contacts')
            ->where('id', $supplierId)
            ->first(['id', 'name', 'commercial_name']);

        if (! $contact) {
            return 'Proveedor #' . $supplierId;
        }

        return trim((string) ($contact->commercial_name ?: $contact->name ?: ('Proveedor #' . $supplierId)));
    }

    protected static function supplierIdByName(?string $supplierName): ?int
    {
        $supplierName = trim((string) $supplierName);

        if ($supplierName === '' || $supplierName === 'Sin proveedor sugerido' || ! Schema::hasTable('contacts')) {
            return null;
        }

        return DB::table('contacts')
            ->where('is_supplier', true)
            ->where('is_active', true)
            ->where(function ($query) use ($supplierName): void {
                $query
                    ->where('name', $supplierName)
                    ->orWhere('commercial_name', $supplierName);
            })
            ->value('id');
    }
PHPRESOURCE;

foreach (['supplierOptions', 'supplierLabel', 'supplierIdByName'] as $methodName) {
    $pattern = '/\n\s+protected static function ' . preg_quote($methodName, '/') . '\s*\(.*?\n\s+}\n/s';
    $text = preg_replace($pattern, "\n", $text);
}

$insertBefore = strpos($text, "    protected static function productOptions");

if ($insertBefore === false) {
    $insertBefore = strrpos($text, "\n}");
}

if ($insertBefore === false) {
    throw new RuntimeException('No encontré dónde insertar métodos.');
}

$text = substr($text, 0, $insertBefore) . $methods . "\n" . substr($text, $insertBefore);

file_put_contents($resourcePath, $text);

echo json_encode([
    'generic_contacts' => $createdOrUpdated,
    'supplier_count_after' => DB::table('contacts')->where('is_supplier', true)->where('is_active', true)->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
