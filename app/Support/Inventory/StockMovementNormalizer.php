<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StockMovementNormalizer
{
    public static function normalizeMovement(int $movementId): void
    {
        if ($movementId <= 0 || ! Schema::hasTable('stock_movements')) {
            return;
        }

        $movement = DB::table('stock_movements')->where('id', $movementId)->first();

        if (! $movement) {
            return;
        }

        $kind = static::detectKind($movement);

        if (! in_array($kind, ['receipt', 'delivery'], true)) {
            return;
        }

        $operationTypeId = static::operationTypeId(
            companyId: $movement->company_id ? (int) $movement->company_id : null,
            warehouseId: $movement->warehouse_id ? (int) $movement->warehouse_id : null,
            kind: $kind,
        );

        $reference = static::nextReference(
            kind: $kind,
            sourceLocationId: $movement->source_location_id ? (int) $movement->source_location_id : null,
            destinationLocationId: $movement->destination_location_id ? (int) $movement->destination_location_id : null,
            excludeMovementId: (int) $movement->id,
        );

        $updates = [
            'reference' => $reference,
            'status' => 'done',
            'confirmed_at' => $movement->confirmed_at ?: now(),
            'updated_at' => now(),
        ];

        if ($operationTypeId) {
            $updates['stock_operation_type_id'] = $operationTypeId;
        }

        if (Schema::hasColumn('stock_movements', 'confirmed_by') && empty($movement->confirmed_by) && auth()->id()) {
            $updates['confirmed_by'] = auth()->id();
        }

        DB::table('stock_movements')
            ->where('id', $movement->id)
            ->update($updates);
    }

    public static function normalizeAllCommercialMovements(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        DB::table('stock_movements')
            ->where(function ($q) {
                $q->where('origin_document', 'like', 'sale_delivery:%')
                    ->orWhere('origin_document', 'like', 'purchase_receipt:%')
                    ->orWhere('reference', 'like', 'ENT-%')
                    ->orWhere('reference', 'like', 'REC-%');
            })
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($id) {
                static::normalizeMovement((int) $id);
            });
    }

    protected static function detectKind(object $movement): ?string
    {
        $origin = (string) ($movement->origin_document ?? '');
        $reference = (string) ($movement->reference ?? '');

        if (str_starts_with($origin, 'sale_delivery:') || str_starts_with($reference, 'ENT-')) {
            return 'delivery';
        }

        if (str_starts_with($origin, 'purchase_receipt:') || str_starts_with($reference, 'REC-')) {
            return 'receipt';
        }

        return null;
    }

    protected static function operationTypeId(?int $companyId, ?int $warehouseId, string $kind): ?int
    {
        if (! Schema::hasTable('stock_operation_types')) {
            return null;
        }

        $base = DB::table('stock_operation_types')
            ->where('operation_kind', $kind);

        if (Schema::hasColumn('stock_operation_types', 'is_active')) {
            $base->where('is_active', true);
        }

        $candidates = [
            ['company_id' => $companyId, 'warehouse_id' => $warehouseId],
            ['company_id' => null, 'warehouse_id' => $warehouseId],
            ['company_id' => $companyId, 'warehouse_id' => null],
            ['company_id' => null, 'warehouse_id' => null],
        ];

        foreach ($candidates as $candidate) {
            $query = clone $base;

            if (Schema::hasColumn('stock_operation_types', 'company_id')) {
                if ($candidate['company_id']) {
                    $query->where('company_id', $candidate['company_id']);
                } else {
                    $query->whereNull('company_id');
                }
            }

            if (Schema::hasColumn('stock_operation_types', 'warehouse_id')) {
                if ($candidate['warehouse_id']) {
                    $query->where('warehouse_id', $candidate['warehouse_id']);
                } else {
                    $query->whereNull('warehouse_id');
                }
            }

            $id = $query
                ->orderBy('id')
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        $data = [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'code' => $kind === 'receipt' ? 'IN' : 'OUT',
            'name' => $kind === 'receipt' ? 'Entrada por compra' : 'Salida por venta',
            'operation_kind' => $kind,
            'reference_prefix' => $kind === 'receipt' ? 'IN' : 'OUT',
            'sequence' => $kind === 'receipt' ? 10 : 20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $columns = Schema::getColumnListing('stock_operation_types');

        $data = array_intersect_key($data, array_flip($columns));

        return (int) DB::table('stock_operation_types')->insertGetId($data);
    }

    protected static function nextReference(string $kind, ?int $sourceLocationId, ?int $destinationLocationId, int $excludeMovementId = 0): string
    {
        $locationId = $kind === 'receipt'
            ? ($destinationLocationId ?: $sourceLocationId)
            : ($sourceLocationId ?: $destinationLocationId);

        $locationCode = static::locationCode($locationId);
        $prefixCode = $kind === 'receipt' ? 'IN' : 'OUT';

        $prefix = $locationCode . '/' . $prefixCode . '/';

        $query = DB::table('stock_movements')
            ->where('reference', 'like', $prefix . '%');

        if ($excludeMovementId > 0) {
            $query->where('id', '!=', $excludeMovementId);
        }

        $lastReference = $query
            ->orderByDesc('reference')
            ->value('reference');

        $nextNumber = 1;

        if ($lastReference && preg_match('/\/(\d+)$/', (string) $lastReference, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    protected static function locationCode(?int $locationId): string
    {
        if (! $locationId || ! Schema::hasTable('stock_locations')) {
            return 'SIN-UBICACION';
        }

        $location = DB::table('stock_locations')
            ->where('id', $locationId)
            ->first();

        if (! $location) {
            return 'SIN-UBICACION';
        }

        $code = '';

        if (Schema::hasColumn('stock_locations', 'code')) {
            $code = trim((string) ($location->code ?? ''));
        }

        if ($code === '' && Schema::hasColumn('stock_locations', 'name')) {
            $code = trim((string) ($location->name ?? ''));
        }

        return static::cleanCode($code) ?: 'SIN-UBICACION';
    }

    protected static function cleanCode(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value;
    }
}
