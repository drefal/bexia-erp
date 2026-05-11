<?php

namespace App\Support;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InventoryInitialLoadImporter
{
    public static function import(?int $companyId, int $warehouseId, int $locationId, string|array|null $fileState): array
    {
        $path = static::resolveUploadedPath($fileState);

        if (! $path || ! is_file($path)) {
            return [
                'ok' => false,
                'errors' => ['No se pudo leer el archivo CSV subido.'],
            ];
        }

        $validation = static::parseAndValidate($path, $companyId, $warehouseId, $locationId);

        if (! empty($validation['errors'])) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
            ];
        }

        $rows = $validation['rows'] ?? [];

        if (empty($rows)) {
            return [
                'ok' => false,
                'errors' => ['No hay líneas con cantidad contada para importar.'],
            ];
        }

        $adjustment = null;

        DB::transaction(function () use ($rows, $companyId, $warehouseId, $locationId, &$adjustment): void {
            $adjustment = StockAdjustment::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'adjustment_at' => now(),
                'adjustment_date' => now()->toDateString(),
                'status' => 'draft',
                'reason' => static::importReason($warehouseId),
                'notes' => static::importNotes($locationId),
                'created_by' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $row['product_id'],
                    'product_variant_id' => $row['variant_id'],
                    'lot_id' => null,
                    'current_quantity' => $row['current_quantity'],
                    'counted_quantity' => $row['counted_quantity'],
                    'difference_quantity' => $row['counted_quantity'] - $row['current_quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'notes' => $row['notes'],
                ]);
            }
        });

        return [
            'ok' => true,
            'adjustment' => $adjustment,
            'lines' => count($rows),
            'skipped_blank' => (int) ($validation['skipped_blank'] ?? 0),
        ];
    }

    protected static function parseAndValidate(string $path, ?int $companyId, int $warehouseId, int $locationId): array
    {
        if (! Schema::hasTable('products')) {
            return [
                'errors' => ['No existe la tabla de productos.'],
            ];
        }

        if (! Schema::hasTable('stock_adjustments') || ! Schema::hasTable('stock_adjustment_lines')) {
            return [
                'errors' => ['No existen las tablas de ajustes de inventario.'],
            ];
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            return [
                'errors' => ['No se pudo abrir el CSV.'],
            ];
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            return [
                'errors' => ['El CSV está vacío.'],
            ];
        }

        $firstLine = static::toUtf8($firstLine);
        $delimiter = static::detectDelimiter($firstLine);
        $headers = static::normalizeHeaders(str_getcsv($firstLine, $delimiter));

        $required = [
            'product_id' => ['producto id', 'product id', 'producto_id', 'product_id'],
            'counted_quantity' => ['cantidad contada', 'cantidad_contada', 'counted quantity', 'counted_quantity'],
        ];

        $columns = [];

        foreach ($required as $key => $aliases) {
            $columns[$key] = static::findColumn($headers, $aliases);

            if ($columns[$key] === null) {
                fclose($handle);

                return [
                    'errors' => ['Falta columna requerida: ' . $aliases[0]],
                ];
            }
        }

        $columns['variant_id'] = static::findColumn($headers, ['variante id', 'variant id', 'variante_id', 'variant_id']);
        $columns['warehouse_id'] = static::findColumn($headers, ['almacen id', 'almacén id', 'warehouse id', 'warehouse_id']);
        $columns['location_id'] = static::findColumn($headers, ['ubicacion id', 'ubicación id', 'location id', 'location_id']);
        $columns['unit_cost'] = static::findColumn($headers, ['costo promedio', 'costo prom', 'costo', 'unit cost', 'unit_cost']);
        $columns['notes'] = static::findColumn($headers, ['notas', 'notes']);

        $errors = [];
        $rows = [];
        $seen = [];
        $lineNumber = 1;
        $skippedBlank = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = static::toUtf8($line);

            if (trim($line) === '') {
                continue;
            }

            $data = str_getcsv($line, $delimiter);

            if (static::rowIsEmpty($data)) {
                continue;
            }

            $countedRaw = static::cell($data, $columns['counted_quantity']);

            if (trim($countedRaw) === '') {
                $skippedBlank++;
                continue;
            }

            $productIdRaw = static::cell($data, $columns['product_id']);
            $variantIdRaw = $columns['variant_id'] !== null ? static::cell($data, $columns['variant_id']) : '';

            $productId = static::parseInteger($productIdRaw);
            $variantId = trim($variantIdRaw) !== '' ? static::parseInteger($variantIdRaw) : null;

            if (! $productId) {
                $errors[] = "Línea {$lineNumber}: Producto ID inválido.";
                continue;
            }

            if ($columns['warehouse_id'] !== null) {
                $csvWarehouseId = static::parseInteger(static::cell($data, $columns['warehouse_id']));

                if ($csvWarehouseId && $csvWarehouseId !== $warehouseId) {
                    $errors[] = "Línea {$lineNumber}: el Almacén ID del CSV no coincide con el seleccionado.";
                }
            }

            if ($columns['location_id'] !== null) {
                $csvLocationId = static::parseInteger(static::cell($data, $columns['location_id']));

                if ($csvLocationId && $csvLocationId !== $locationId) {
                    $errors[] = "Línea {$lineNumber}: la Ubicación ID del CSV no coincide con la seleccionada.";
                }
            }

            $counted = static::parseDecimal($countedRaw);

            if ($counted === null) {
                $errors[] = "Línea {$lineNumber}: Cantidad contada inválida.";
                continue;
            }

            if ($counted < 0) {
                $errors[] = "Línea {$lineNumber}: La cantidad contada no puede ser negativa en esta importación.";
                continue;
            }

            $key = $productId . '|' . ($variantId ?: 0);

            if (isset($seen[$key])) {
                $errors[] = "Línea {$lineNumber}: Producto/variante duplicado en el CSV.";
                continue;
            }

            $seen[$key] = true;

            $product = static::productRow($productId, $companyId);

            if (! $product) {
                $errors[] = "Línea {$lineNumber}: Producto ID {$productId} no existe o no pertenece a la empresa.";
                continue;
            }

            $variant = null;

            if ($variantId) {
                $variant = static::variantRow($variantId, $productId, $companyId);

                if (! $variant) {
                    $errors[] = "Línea {$lineNumber}: Variante ID {$variantId} no existe o no pertenece al producto.";
                    continue;
                }
            }

            if (static::productIsTracked($product) || ($variant && static::productIsTracked($variant))) {
                $errors[] = "Línea {$lineNumber}: El producto/variante tiene seguimiento por lote o serie. Debe ajustarse en el flujo especial de seguimiento.";
                continue;
            }

            if (static::hasTrackedQuant($companyId, $warehouseId, $locationId, $productId, $variantId)) {
                $errors[] = "Línea {$lineNumber}: Existen existencias con lote/serie para este producto/variante. No se puede importar en conteo sin seguimiento.";
                continue;
            }

            $currentQuantity = static::currentQuantity($companyId, $warehouseId, $locationId, $productId, $variantId);

            $unitCost = null;

            if ($columns['unit_cost'] !== null) {
                $unitCostRaw = static::cell($data, $columns['unit_cost']);
                $unitCost = trim($unitCostRaw) !== '' ? static::parseDecimal($unitCostRaw) : null;

                if ($unitCost === null && trim($unitCostRaw) !== '') {
                    $errors[] = "Línea {$lineNumber}: Costo promedio inválido.";
                    continue;
                }
            }

            if ($unitCost === null) {
                $unitCost = static::averageCost($companyId, $warehouseId, $locationId, $productId, $variantId);
            }

            if ($unitCost === null) {
                $unitCost = static::productCost($product, $variant);
            }

            $rows[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'current_quantity' => $currentQuantity,
                'counted_quantity' => $counted,
                'unit_cost' => $unitCost,
                'notes' => $columns['notes'] !== null ? trim(static::cell($data, $columns['notes'])) : '',
            ];

            if (count($errors) >= 80) {
                $errors[] = 'Hay demasiados errores. Corrige el archivo y vuelve a intentar.';
                break;
            }
        }

        fclose($handle);

        return [
            'errors' => $errors,
            'rows' => $rows,
            'skipped_blank' => $skippedBlank,
        ];
    }

    protected static function resolveUploadedPath(string|array|null $fileState): ?string
    {
        if (is_array($fileState)) {
            $fileState = reset($fileState) ?: null;
        }

        if (! is_string($fileState) || trim($fileState) === '') {
            return null;
        }

        $fileState = trim($fileState);

        $candidates = [
            $fileState,
            storage_path('app/' . ltrim($fileState, '/')),
            storage_path('app/private/' . ltrim($fileState, '/')),
            storage_path('app/public/' . ltrim($fileState, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        if (Storage::disk('local')->exists($fileState)) {
            return Storage::disk('local')->path($fileState);
        }

        if (Storage::disk('public')->exists($fileState)) {
            return Storage::disk('public')->path($fileState);
        }

        return null;
    }

    protected static function detectDelimiter(string $line): string
    {
        $semicolon = substr_count($line, ';');
        $comma = substr_count($line, ',');
        $tab = substr_count($line, "\t");

        if ($tab > $semicolon && $tab > $comma) {
            return "\t";
        }

        return $semicolon >= $comma ? ';' : ',';
    }

    protected static function normalizeHeaders(array $headers): array
    {
        return array_map(fn ($header): string => static::normalizeHeader((string) $header), $headers);
    }

    protected static function normalizeHeader(string $value): string
    {
        $value = static::toUtf8($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
            'ü' => 'u',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim((string) $value);
    }

    protected static function findColumn(array $headers, array $aliases): ?int
    {
        $normalizedAliases = array_map(fn ($alias): string => static::normalizeHeader($alias), $aliases);

        foreach ($headers as $index => $header) {
            if (in_array($header, $normalizedAliases, true)) {
                return (int) $index;
            }
        }

        return null;
    }

    protected static function cell(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    protected static function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected static function parseInteger(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9]/', '', $value);

        if ($value === '') {
            return null;
        }

        return (int) $value;
    }

    protected static function parseDecimal(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(["\xc2\xa0", ' ', '$'], '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastComma !== false) {
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected static function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    protected static function productRow(int $productId, ?int $companyId): ?object
    {
        $query = DB::table('products')->where('id', $productId);

        if (Schema::hasColumn('products', 'company_id') && $companyId) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($query): void {
                $query
                    ->where('is_variant', false)
                    ->orWhereNull('is_variant');
            });
        }

        return $query->first();
    }

    protected static function variantRow(int $variantId, int $productId, ?int $companyId): ?object
    {
        $query = DB::table('products')
            ->where('id', $variantId);

        if (Schema::hasColumn('products', 'parent_product_id')) {
            $query->where('parent_product_id', $productId);
        }

        if (Schema::hasColumn('products', 'company_id') && $companyId) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where('is_variant', true);
        }

        return $query->first();
    }

    protected static function productIsTracked(object $product): bool
    {
        $stringColumns = [
            'tracking',
            'tracking_type',
            'tracking_method',
            'inventory_tracking',
            'lot_serial_tracking',
            'tracking_mode',
        ];

        $untracked = [
            '',
            'none',
            'no',
            'false',
            '0',
            'no_tracking',
            'untracked',
            'sin_seguimiento',
            'sin seguimiento',
            'ninguno',
        ];

        foreach ($stringColumns as $column) {
            if (! Schema::hasColumn('products', $column)) {
                continue;
            }

            $value = mb_strtolower(trim((string) ($product->{$column} ?? '')), 'UTF-8');

            if (! in_array($value, $untracked, true)) {
                return true;
            }
        }

        $booleanColumns = [
            'has_tracking',
            'is_tracked',
            'track_lots',
            'track_serials',
            'requires_lot',
            'requires_serial',
            'use_lots',
            'use_serials',
            'lot_tracking',
            'serial_tracking',
        ];

        foreach ($booleanColumns as $column) {
            if (Schema::hasColumn('products', $column) && (bool) ($product->{$column} ?? false)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasTrackedQuant(?int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId): bool
    {
        if (! Schema::hasTable('stock_quants') || ! Schema::hasColumn('stock_quants', 'lot_id')) {
            return false;
        }

        $query = DB::table('stock_quants')
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->whereNotNull('lot_id');

        if (Schema::hasColumn('stock_quants', 'company_id')) {
            $companyId
                ? $query->where('company_id', $companyId)
                : $query->whereNull('company_id');
        }

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        return $query->exists();
    }

    protected static function currentQuantity(?int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0;
        }

        $query = DB::table('stock_quants')
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if (Schema::hasColumn('stock_quants', 'company_id')) {
            $companyId
                ? $query->where('company_id', $companyId)
                : $query->whereNull('company_id');
        }

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        return (float) $query->sum('quantity');
    }

    protected static function averageCost(?int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId): ?float
    {
        if (! Schema::hasTable('stock_quants') || ! Schema::hasColumn('stock_quants', 'average_cost')) {
            return null;
        }

        $query = DB::table('stock_quants')
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if (Schema::hasColumn('stock_quants', 'company_id')) {
            $companyId
                ? $query->where('company_id', $companyId)
                : $query->whereNull('company_id');
        }

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        $value = $query->value('average_cost');

        return $value !== null ? (float) $value : null;
    }


    protected static function importReason(int $warehouseId): string
    {
        return 'Conteo físico CSV '
            . now()->format('d/m/Y H:i')
            . ' - '
            . static::warehouseLabel($warehouseId);
    }

    protected static function importNotes(int $locationId): string
    {
        return 'Ubicación: '
            . static::locationLabel($locationId)
            . '. Ajuste creado desde importación CSV. No afecta existencias hasta confirmar.';
    }

    protected static function warehouseLabel(int $warehouseId): string
    {
        return static::labelFromTable('warehouses', $warehouseId, ['code'], ['name']);
    }

    protected static function locationLabel(int $locationId): string
    {
        return static::labelFromTable('stock_locations', $locationId, ['code'], ['name']);
    }

    protected static function labelFromTable(string $table, int $id, array $codeColumns, array $nameColumns): string
    {
        if (! Schema::hasTable($table)) {
            return '#' . $id;
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '#' . $id;
        }

        $code = '';
        $name = '';

        foreach ($codeColumns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $value = trim((string) ($row->{$column} ?? ''));

            if ($value !== '') {
                $code = $value;
                break;
            }
        }

        foreach ($nameColumns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $value = trim((string) ($row->{$column} ?? ''));

            if ($value !== '') {
                $name = $value;
                break;
            }
        }

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: ('#' . $id));
    }

    protected static function productCost(object $product, ?object $variant): ?float
    {
        foreach ([$variant, $product] as $row) {
            if (! $row) {
                continue;
            }

            foreach (['standard_cost', 'purchase_price', 'last_purchase_cost', 'cost'] as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    continue;
                }

                $value = $row->{$column} ?? null;

                if ($value !== null && (float) $value > 0) {
                    return (float) $value;
                }
            }
        }

        return null;
    }
}
