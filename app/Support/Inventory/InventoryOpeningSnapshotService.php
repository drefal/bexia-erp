<?php

namespace App\Support\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class InventoryOpeningSnapshotService
{
    public const SOURCE = 'odoo_stock_quant_v5_82_3k2c';

    public const SNAPSHOT = '2026-07-10 22:12:47';

    public function rows(array $filters): Collection
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);

        if ($companyId <= 0 || $warehouseId <= 0) {
            throw new RuntimeException(
                'La apertura requiere empresa y almacen.'
            );
        }

        foreach ([
            'inventory_stock_balances',
            'stock_lots',
            'warehouses',
            'products',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    'Tabla requerida no disponible: ' . $table
                );
            }
        }

        if (! DB::table('warehouses')
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->exists()) {
            throw new RuntimeException(
                'El almacen no corresponde a la empresa.'
            );
        }

        $query = DB::table('inventory_stock_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->where('b.company_id', $companyId)
            ->where('b.warehouse_id', $warehouseId)
            ->where('b.source', self::SOURCE)
            ->where('b.snapshot_at', self::SNAPSHOT)
            ->select([
                'b.id',
                'b.company_id',
                'b.warehouse_id',
                'b.inventory_location_id',
                'b.product_id',
                'p.name as product_name',
                'b.odoo_product_id',
                'b.odoo_location_id',
                'b.odoo_lot_id',
                'b.lot_name',
                'b.quantity',
                'b.reserved_quantity',
                'b.available_quantity',
                'b.source',
                'b.snapshot_at',
            ]);

        if (! empty($filters['location_id'])) {
            $query->where(
                'b.inventory_location_id',
                (int) $filters['location_id']
            );
        }

        if (! empty($filters['product_id'])) {
            $query->where(
                'b.product_id',
                (int) $filters['product_id']
            );
        }

        $rows = $query->orderBy('b.id')->limit(50001)->get();

        if ($rows->count() > 50000) {
            throw new RuntimeException(
                'Demasiadas lineas de apertura; filtre el producto.'
            );
        }

        /*
         * Excluir unicamente balances que la migracion identifico
         * expresamente como duplicados de serie.
         * El registro original NO se elimina ni se modifica.
         */
        $excluded = [];

        $lotsWithDecisions = DB::table('stock_lots')
            ->where('company_id', $companyId)
            ->whereNotNull('metadata')
            ->select('id', 'product_id', 'lot_number', 'metadata')
            ->get();

        foreach ($lotsWithDecisions as $lot) {
            $metadata = json_decode(
                (string) $lot->metadata,
                true
            );

            if (! is_array($metadata)) {
                continue;
            }

            $decision = $metadata['duplicate_serial_decision']
                ?? null;

            if (! is_array($decision)) {
                continue;
            }

            $excludedId = (int) (
                $decision['excluded_balance_id'] ?? 0
            );

            $canonicalId = (int) (
                $decision['canonical_balance_id'] ?? 0
            );

            if ($excludedId <= 0 || $canonicalId <= 0
                || $excludedId === $canonicalId) {
                throw new RuntimeException(
                    'Decision de serie duplicada invalida en lote '
                    . $lot->id
                );
            }

            if (isset($excluded[$excludedId])) {
                throw new RuntimeException(
                    'Balance excluido por varias decisiones: '
                    . $excludedId
                );
            }

            $excluded[$excludedId] = [
                'product_id' => (int) $lot->product_id,
                'lot_number' => (string) $lot->lot_number,
            ];
        }

        $rows = $rows->filter(
            static function ($row) use ($excluded): bool {
                $id = (int) $row->id;

                if (! isset($excluded[$id])) {
                    return true;
                }

                $decision = $excluded[$id];

                if (
                    (int) $row->product_id
                    !== $decision['product_id']
                    || (string) $row->lot_name
                    !== $decision['lot_number']
                ) {
                    throw new RuntimeException(
                        'Identidad inconsistente del balance excluido '
                        . $id
                    );
                }

                return false;
            }
        )->values();

        return $rows->map(function ($row) use ($companyId) {
            $lotId = null;

            if ($row->odoo_lot_id !== null) {
                $lotName = trim((string) ($row->lot_name ?? ''));

                if ($lotName === '') {
                    throw new RuntimeException(
                        'Lote Odoo sin nombre: balance ' . $row->id
                    );
                }

                $matching = DB::table('stock_lots')
                    ->where('company_id', $companyId)
                    ->where('product_id', $row->product_id)
                    ->where('lot_number', $lotName)
                    ->pluck('id');

                if ($matching->count() !== 1) {
                    throw new RuntimeException(
                        'No se pudo resolver un lote unico para balance '
                        . $row->id . ' (' . $lotName . ')'
                    );
                }

                $lotId = (int) $matching->first();
            }

            return (object) [
                'balance_id' => (int) $row->id,
                'company_id' => (int) $row->company_id,
                'warehouse_id' => (int) $row->warehouse_id,
                'location_id' => (int) $row->inventory_location_id,
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'lot_id' => $lotId,
                'lot_number' => $row->lot_name,
                'quantity' => (float) $row->quantity,
                'reserved_quantity' => (float) $row->reserved_quantity,
                'available_quantity' => (float) $row->available_quantity,
                'odoo_product_id' => $row->odoo_product_id,
                'odoo_location_id' => $row->odoo_location_id,
                'odoo_lot_id' => $row->odoo_lot_id,
                'source' => $row->source,
                'snapshot_at' => $row->snapshot_at,
            ];
        });
    }
}
