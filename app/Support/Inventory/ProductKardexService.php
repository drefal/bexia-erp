<?php

namespace App\Support\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductKardexService
{
    public const METHOD_AUTO = 'auto';
    public const METHOD_AVERAGE = 'average';
    public const METHOD_FIFO = 'fifo';
    public const METHOD_STANDARD = 'standard';
    public const METHOD_RECORDED = 'recorded';

    /**
     * INV11 FIX1D. Apertura historica documental (no transaccion).
     * Fase controlada: empresa y almacen seleccionados; variante/serie no
     * se infieren. El saldo en unidades se reconstruye cronologicamente.
     * La valoracion afectada por la apertura NO esta certificada.
     */
    public function rows(array $filters = []): Collection
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);

        if ($companyId <= 0 || $warehouseId <= 0
            || ! empty($filters['product_variant_id'])
            || ! empty($filters['stock_serial_number_id'])
            || (! empty($filters['product_search']) && empty($filters['product_id']))
            || ! \App\Support\Security\InventoryTenantScope::isAllowedCompany($companyId)
        ) {
            return $this->movementRows($filters);
        }

        // Garantia contra mezcla de empresas en la seleccion de almacen.
        if (! DB::table('warehouses')->where('id', $warehouseId)
            ->where('company_id', $companyId)->exists()) {
            return collect();
        }

        $opening = app(InventoryOpeningSnapshotService::class)->rows([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'location_id' => $filters['location_id'] ?? null,
            'product_id' => $filters['product_id'] ?? null,
        ]);

        if (! empty($filters['lot_id'])) {
            $lotId = (int) $filters['lot_id'];
            $opening = $opening->filter(
                static fn ($r) => (int) ($r->lot_id ?? 0) === $lotId
            )->values();
        }

        // Ninguna apertura para estos filtros: historial sin alteraciones.
        if ($opening->isEmpty()) {
            return $this->movementRows($filters);
        }

        $fullFilters = $filters;
        unset($fullFilters['date_from'], $fullFilters['date_to']);
        $fullFilters['status'] = 'done';
        $movements = $this->movementRows($fullFilters);
        $snapshot = InventoryOpeningSnapshotService::SNAPSHOT;
        $start = ! empty($filters['date_from']) ? (string) $filters['date_from'] : null;
        $end = ! empty($filters['date_to']) ? (string) $filters['date_to'] : null;
        $warehouseName = (string) DB::table('warehouses')
            ->where('id', $warehouseId)->value('name');
        $companyName = (string) DB::table('companies')
            ->where('id', $companyId)->value('name');

        // Agrupar solo las filas del mismo lote; nunca fundir lotes.
        $initial = [];
        foreach ($opening as $item) {
            $key = implode('|', [
                $companyId, $warehouseId, (int) $item->product_id,
                0, (int) ($item->lot_id ?? 0), 0,
            ]);
            if (! isset($initial[$key])) {
                $initial[$key] = [
                    'qty' => 0.0,
                    'item' => $item,
                ];
            }
            $initial[$key]['qty'] = round(
                $initial[$key]['qty'] + (float) $item->quantity, 6
            );
        }

        $entries = collect();
        foreach ($initial as $key => $initialRow) {
            $item = $initialRow['item'];
            $qty = $initialRow['qty'];
            $entries->push((object) [
                'id' => 'odoo-' . $item->balance_id,
                'balance_key' => $key,
                'stock_movement_id' => null,
                'company_id' => $companyId,
                'company_name' => $companyName,
                'date' => $snapshot,
                'reference' => 'SALDO INICIAL ODOO',
                'origin_document' => 'Fotografia historica de inventario',
                'status' => 'done',
                'warehouse_name' => $warehouseName,
                'source_location_name' => null,
                'destination_location_name' => null,
                'source_type' => 'odoo_opening',
                'source_line_type' => 'snapshot',
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_variant_id' => null,
                'variant_name' => null,
                'lot_id' => $item->lot_id,
                'lot_number' => $item->lot_number,
                'stock_serial_number_id' => null,
                'serial_number' => null,
                'direction' => 'opening',
                'quantity' => $qty,
                'in_qty' => 0.0,
                'out_qty' => 0.0,
                'recorded_unit_cost' => 0.0,
                'recorded_total_cost' => 0.0,
                'applied_unit_cost' => 0.0,
                'movement_value' => 0.0,
                'signed_value' => 0.0,
                'balance_qty' => $qty,
                'balance_value' => null,
                'average_cost' => null,
                'valuation_method' => 'opening',
                'costing_method' => null,
                'cost_source' => 'odoo_stock_snapshot_uncertified',
                'notes' => 'Apertura documental: no es entrada fisica; costo no certificado',
            ]);
        }

        $balances = [];
        $all = $entries->concat($movements->filter(
            static function ($row) use ($snapshot, $initial): bool {
                // Movimientos anteriores a la apertura ya estan contenidos
                // en la foto de Odoo para las mismas claves.
                return ! isset($initial[$row->balance_key])
                    || (string) $row->date > $snapshot;
            }
        ))->sort(function ($a, $b) {
            $dateCmp = strcmp((string) $a->date, (string) $b->date);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return ($a->source_type === 'odoo_opening' ? -1 : 1)
                <=> ($b->source_type === 'odoo_opening' ? -1 : 1);
        })->values();

        $result = $all->map(function ($row) use (&$balances, $initial) {
            $key = $row->balance_key;
            if ($row->source_type === 'odoo_opening') {
                $balances[$key] = (float) $row->quantity;
                return $row;
            }
            if (isset($initial[$key])) {
                $balances[$key] = round(
                    ($balances[$key] ?? $initial[$key]['qty'])
                    + (float) $row->in_qty - (float) $row->out_qty,
                    6
                );
                $row->balance_qty = $balances[$key];
                $row->balance_value = null;
                $row->average_cost = null;
            }
            return $row;
        });

        // La apertura permanece visible como antecedente aunque date_from
        // sea posterior. Nunca se fecha falsamente dentro del periodo.
        return $result->filter(static function ($row) use ($start, $end): bool {
            $date = substr((string) $row->date, 0, 10);
            if ($end && $date > $end) {
                return false;
            }
            if ($row->source_type === 'odoo_opening') {
                return true;
            }
            return ! $start || $date >= $start;
        })->values();
    }

    protected function movementRows(array $filters = []): Collection
    {
        if (! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_movements')) {
            return collect();
        }

        $query = DB::table('stock_movement_lines as l')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'l.product_variant_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->leftJoin('stock_serial_numbers as serial', 'serial.id', '=', 'l.stock_serial_number_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('warehouses as dw', 'dw.id', '=', 'm.destination_warehouse_id')
            ->leftJoin('stock_locations as sl_from', 'sl_from.id', '=', 'm.source_location_id')
            ->leftJoin('stock_locations as sl_to', 'sl_to.id', '=', 'm.destination_location_id')
            ->select([
                'l.id',
                'l.stock_movement_id',
                'm.company_id',
                'c.name as company_name',
                'm.warehouse_id',
                'w.name as warehouse_name',
                'm.destination_warehouse_id',
                'dw.name as destination_warehouse_name',
                'm.transit_location_id',
                'm.source_location_id',
                'sl_from.name as source_location_name',
                'sl_from.warehouse_id as source_location_warehouse_id',
                'sl_from.tracks_stock as source_tracks_stock',
                'm.destination_location_id',
                'sl_to.name as destination_location_name',
                'sl_to.warehouse_id as destination_location_warehouse_id',
                'sl_to.tracks_stock as destination_tracks_stock',
                'm.reference',
                'm.origin_document',
                'm.status',
                'm.movement_at',
                'm.created_at as movement_created_at',

                'l.source_type',
                'l.source_id',
                'l.source_line_type',
                'l.source_line_id',
                'l.product_id',
                'p.name as product_name',
                'p.company_id as product_company_id',
                'p.product_category_id',
                'p.costing_method as product_costing_method',
                'p.standard_cost as product_standard_cost',
                'p.average_cost_without_tax as product_average_cost_without_tax',
                'p.last_purchase_cost as product_last_purchase_cost',

                'l.product_variant_id',
                'v.name as variant_name',
                'v.costing_method as variant_costing_method',
                'v.standard_cost as variant_standard_cost',
                'v.average_cost_without_tax as variant_average_cost_without_tax',
                'v.last_purchase_cost as variant_last_purchase_cost',

                'pc.costing_method as category_costing_method',
                'c.default_costing_method as company_default_costing_method',

                'l.lot_id',
                'lot.lot_number as lot_number',
                'l.stock_serial_number_id',
                'serial.serial_number as serial_number',
                'l.requested_quantity',
                'l.done_quantity',
                'l.unit_cost',
                'l.total_cost',
                'l.costing_method',
                'l.cost_source',
                'l.notes',
            ]);

        // INV10A: conservar operaciones anteriores al periodo.
        $calculationFilters = $filters;
        unset(
            $calculationFilters['date_from'],
            $calculationFilters['date_to']
        );
        $calculationFilters['status'] = 'done';
        $this->applyFilters($query, $calculationFilters);

        $records = $query
            ->orderByRaw('coalesce(m.movement_at, m.created_at, l.created_at) asc')
            ->orderBy('l.id')
            ->limit(50001)
            ->get();

        if ($records->count() > 50000) {
            throw new \RuntimeException(
                'El Kardex supera 50,000 líneas. ' .
                'Seleccione un producto o almacén.'
            );
        }

        /*
         * INV11 FIX1F:
         * Incidencias de mercancía faltante asociadas a cada línea.
         *
         * La cantidad faltante NO se recibe en el destino durante
         * el traslado original. Su resolución puede generar
         * posteriormente una entrada real independiente.
         *
         * No se modifica ningún movimiento ni incidencia.
         */
        $missingByLine = [];

        if (
            Schema::hasTable('stock_movement_incidents')
            && $records->isNotEmpty()
        ) {
            $lineIds = $records->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            foreach (array_chunk($lineIds, 1000) as $chunk) {
                $incidents = DB::table('stock_movement_incidents')
                    ->whereIn('stock_movement_line_id', $chunk)
                    ->where('incident_type', 'missing')
                    ->whereIn('status', ['open', 'resolved'])
                    ->select(
                        'id',
                        'company_id',
                        'stock_movement_id',
                        'stock_movement_line_id',
                        'quantity'
                    )
                    ->get();

                foreach ($incidents as $incident) {
                    $lineId = (int) $incident->stock_movement_line_id;

                    $original = $records->first(
                        static fn ($r) => (int) $r->id === $lineId
                    );

                    if (
                        ! $original
                        || (int) $original->company_id
                            !== (int) $incident->company_id
                        || (int) $original->stock_movement_id
                            !== (int) $incident->stock_movement_id
                    ) {
                        throw new \RuntimeException(
                            'Incidencia de faltante inconsistente: '
                            . $incident->id
                        );
                    }

                    $missingByLine[$lineId] = round(
                        ($missingByLine[$lineId] ?? 0)
                            + (float) $incident->quantity,
                        6
                    );
                }
            }

            foreach ($records as $original) {
                $missing = (float) (
                    $missingByLine[(int) $original->id] ?? 0
                );

                $documentQty = abs((float) (
                    $original->done_quantity
                    ?? $original->requested_quantity
                    ?? 0
                ));

                if (
                    $missing < 0
                    || $missing > $documentQty + 0.000001
                ) {
                    throw new \RuntimeException(
                        'Faltante excede cantidad de línea: '
                        . $original->id
                    );
                }
            }
        }

        $selectedMethod = $this->normalizeMethod((string) ($filters['valuation_method'] ?? self::METHOD_AUTO));
        $warehouseFilter = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $locationFilter = ! empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $states = [];

        $mapped = $records->map(function ($row) use (&$states, $selectedMethod, $warehouseFilter, $locationFilter, $missingByLine) {
            if ($warehouseFilter !== null) {
                $row->effective_warehouse_id = $warehouseFilter;
                $row->effective_warehouse_name =
                    (int) ($row->destination_warehouse_id ?? 0) === $warehouseFilter
                        ? ($row->destination_warehouse_name ?? $row->warehouse_name)
                        : $row->warehouse_name;
            } else {
                $row->effective_warehouse_id = $row->warehouse_id;
                $row->effective_warehouse_name = $row->warehouse_name;
            }
            $key = $this->balanceKey($row);
            $method = $selectedMethod === self::METHOD_AUTO
                ? $this->effectiveCostingMethod($row)
                : $selectedMethod;

            if (! isset($states[$key])) {
                $states[$key] = $this->initialState($row);
            }

            $direction = $this->direction($row, $warehouseFilter, $locationFilter);
            $qty = abs((float) ($row->done_quantity ?? $row->requested_quantity ?? 0));
            /*
             * La cantidad documental sigue siendo $qty.
             * Solo la cantidad recibida modifica el saldo.
             *
             * No descontar faltantes en:
             * - Salida del almacén origen.
             * - Movimientos de cuarentena.
             * - Resoluciones posteriores.
             * - Consultas sin almacén destino específico.
             */
            $effectiveQty = $qty;
            $missingQty = (float) (
                $missingByLine[(int) $row->id] ?? 0
            );

            if (
                $missingQty > 0
                && $direction === 'in'
                && $warehouseFilter !== null
                && (int) (
                    $row->destination_location_warehouse_id ?? 0
                ) === $warehouseFilter
            ) {
                $effectiveQty = max(
                    0.0,
                    round($qty - $missingQty, 6)
                );
            }


            /*
             * Un traslado entre ubicaciones del mismo almacén no cambia
             * la cantidad ni el valor total del almacén.
             *
             * Conservamos $qty para mostrar el movimiento, pero usamos
             * cero para el motor de valoración cuando direction=transfer.
             *
             * Si existe filtro de ubicación, direction() devuelve in/out
             * y el movimiento sí afecta correctamente el saldo de esa
             * ubicación.
             */
            $valuationQty = $direction === 'transfer'
                ? 0.0
                : $effectiveQty;

            $valuation = match ($method) {
                self::METHOD_FIFO => $this->applyFifo(
                    $states[$key],
                    $row,
                    $direction,
                    $valuationQty
                ),
                self::METHOD_STANDARD => $this->applyStandard(
                    $states[$key],
                    $row,
                    $direction,
                    $valuationQty
                ),
                self::METHOD_RECORDED => $this->applyRecorded(
                    $states[$key],
                    $row,
                    $direction,
                    $valuationQty
                ),
                default => $this->applyAverage(
                    $states[$key],
                    $row,
                    $direction,
                    $valuationQty
                ),
            };

            return (object) [
                'id' => $row->id,
                'balance_key' => $key,
                'stock_movement_id' => $row->stock_movement_id,
                'company_id' => $row->company_id,
                'company_name' => $row->company_name,
                'date' => $row->movement_at ?: $row->movement_created_at,
                'reference' => $row->reference,
                'origin_document' => $row->origin_document,
                'status' => $row->status,
                'warehouse_name' => $row->effective_warehouse_name ?? $row->warehouse_name,
                'source_location_name' => $row->source_location_name,
                'destination_location_name' => $row->destination_location_name,
                'source_type' => $row->source_type,
                'source_line_type' => $row->source_line_type,
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'product_variant_id' => $row->product_variant_id,
                'variant_name' => $row->variant_name,
                'lot_id' => $row->lot_id,
                'lot_number' => $row->lot_number,
                'stock_serial_number_id' => $row->stock_serial_number_id,
                'serial_number' => $row->serial_number,

                'direction' => $direction,
                'quantity' => $qty,
                'in_qty' => $direction === 'in' ? $effectiveQty : 0.0,
                'out_qty' => $direction === 'out' ? $qty : 0.0,

                'recorded_unit_cost' => (float) ($row->unit_cost ?? 0),
                'recorded_total_cost' => (float) ($row->total_cost ?? 0),
                'applied_unit_cost' => $valuation['unit_cost'],
                'movement_value' => $valuation['movement_value'],
                'signed_value' => $direction === 'out' ? -abs($valuation['movement_value']) : abs($valuation['movement_value']),
                'balance_qty' => $states[$key]['qty'],
                'balance_value' => $states[$key]['value'],
                'average_cost' => $states[$key]['average_cost'],
                'valuation_method' => $method,

                'costing_method' => $row->costing_method,
                'cost_source' => $row->cost_source,
                'notes' => $row->notes,
            ];
        });

        // El rango de fechas limita las filas visibles,
        // pero no reinicia el saldo acumulado.
        $from = ! empty($filters['date_from'])
            ? (string) $filters['date_from']
            : null;
        $to = ! empty($filters['date_to'])
            ? (string) $filters['date_to']
            : null;

        return $mapped->filter(
            static function ($row) use ($from, $to): bool {
                $date = substr(
                    (string) ($row->date ?? ''),
                    0,
                    10
                );

                if ($from && $date < $from) {
                    return false;
                }

                if ($to && $date > $to) {
                    return false;
                }

                return true;
            }
        )->values();
    }

    public function summary(array $filters = []): array
    {
        $rows = $this->rows($filters);
        $lastByKey = [];

        foreach ($rows as $row) {
            $lastByKey[$row->balance_key] = $row;
        }

        $methods = $rows
            ->pluck('valuation_method')
            ->filter()
            ->unique()
            ->values();

        $uncertified = $rows->contains(
            static fn ($r) => $r->balance_value === null
        );

        return [
            'rows' => $rows->count(),
            'in_qty' => round((float) $rows->sum('in_qty'), 6),
            'out_qty' => round((float) $rows->sum('out_qty'), 6),
            'balance_qty' => round((float) collect($lastByKey)->sum('balance_qty'), 6),
            'in_value' => round((float) $rows->where('direction', 'in')->sum('movement_value'), 6),
            'out_value' => round((float) $rows->where('direction', 'out')->sum('movement_value'), 6),
            'balance_value' => $uncertified ? null : round((float) collect($lastByKey)->sum('balance_value'), 6),
            'valuation_method' => $methods->count() === 1 ? $methods->first() : 'mixed',
        ];
    }


    /**
     * INV11: conciliacion documental de apertura Odoo.
     *
     * No escribe movimientos ni quants y no modifica el metodo
     * rows() que utiliza la pantalla actual.
     *
     * Solo admite una empresa, almacen y producto especificos.
     */
    public function openingReconciliation(array $filters): array
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
        $productId = (int) ($filters['product_id'] ?? 0);

        if ($companyId <= 0 || $warehouseId <= 0 || $productId <= 0) {
            throw new \InvalidArgumentException(
                'Seleccione empresa, almacen y producto.'
            );
        }

        if (
            ! empty($filters['product_variant_id'])
            || ! empty($filters['stock_serial_number_id'])
        ) {
            throw new \InvalidArgumentException(
                'La conciliacion de apertura por variante o serie '
                . 'requiere validacion adicional.'
            );
        }

        if (! Schema::hasTable('stock_quants')) {
            throw new \RuntimeException('No existe stock_quants.');
        }

        $reader = app(InventoryOpeningSnapshotService::class);

        $openingRows = $reader->rows([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'location_id' => $filters['location_id'] ?? null,
        ]);

        if (! empty($filters['lot_id'])) {
            $lotId = (int) $filters['lot_id'];
            $openingRows = $openingRows
                ->filter(fn ($r) => (int) ($r->lot_id ?? 0) === $lotId)
                ->values();
        }

        $openingQty = round(
            (float) $openingRows->sum('quantity'), 6
        );

        // El lector conserva la fecha exacta de la fotografia
        // y los movimientos anteriores no se suman otra vez.
        $snapshot = InventoryOpeningSnapshotService::SNAPSHOT;

        $movementFilters = [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'location_id' => $filters['location_id'] ?? null,
            'lot_id' => $filters['lot_id'] ?? null,
            'status' => 'done',
            'valuation_method' => 'auto',
        ];

        $movementRows = $this->rows($movementFilters)
            ->filter(
                static fn ($r) =>
                    (string) ($r->date ?? '') > $snapshot
            )
            ->values();

        $movementNet = round(
            (float) $movementRows->sum(
                static fn ($r) =>
                    (float) ($r->in_qty ?? 0)
                    - (float) ($r->out_qty ?? 0)
            ),
            6
        );

        $calculated = round($openingQty + $movementNet, 6);

        $quants = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId);

        if (! empty($filters['location_id'])) {
            $quants->where(
                'location_id', (int) $filters['location_id']
            );
        }

        if (! empty($filters['lot_id'])) {
            $quants->where('lot_id', (int) $filters['lot_id']);
        }

        $actual = round((float) $quants->sum('quantity'), 6);

        return [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'opening_documented' => $openingRows->isNotEmpty(),
            'opening_rows' => $openingRows->count(),
            'opening_qty' => $openingQty,
            'movement_rows' => $movementRows->count(),
            'movement_net' => $movementNet,
            'calculated_qty' => $calculated,
            'actual_qty' => $actual,
            'difference' => round($actual - $calculated, 6),
            'snapshot_at' => $snapshot,
            'source' => InventoryOpeningSnapshotService::SOURCE,
            'valuation_verified' => false,
        ];
    }

    protected function applyAverage(array &$state, object $row, string $direction, float $qty): array
    {
        if ($direction === 'in') {
            $movementValue = $this->recordedLineValue($row, $qty);
            $unitCost = $qty > 0 ? round($movementValue / $qty, 6) : $this->baseUnitCost($row);

            $state['qty'] = round($state['qty'] + $qty, 6);
            $state['value'] = round($state['value'] + $movementValue, 6);
            $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

            return [
                'unit_cost' => $unitCost,
                'movement_value' => round($movementValue, 6),
            ];
        }

        $unitCost = $state['average_cost'] > 0 ? $state['average_cost'] : $this->baseUnitCost($row);
        $movementValue = round($qty * $unitCost, 6);

        $state['qty'] = round($state['qty'] - $qty, 6);
        $state['value'] = round($state['value'] - $movementValue, 6);

        if (abs($state['qty']) < 0.000001) {
            $state['qty'] = 0.0;
            $state['value'] = 0.0;
            $state['average_cost'] = 0.0;
        } elseif ($state['qty'] > 0) {
            $state['average_cost'] = round($state['value'] / $state['qty'], 6);
        }

        return [
            'unit_cost' => $unitCost,
            'movement_value' => $movementValue,
        ];
    }

    protected function applyFifo(array &$state, object $row, string $direction, float $qty): array
    {
        if ($direction === 'in') {
            $unitCost = $this->baseUnitCost($row);
            $movementValue = round($qty * $unitCost, 6);

            if ($qty > 0) {
                $state['layers'][] = [
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                ];
            }

            $state['qty'] = round($state['qty'] + $qty, 6);
            $state['value'] = round($this->fifoLayerValue($state), 6);
            $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

            return [
                'unit_cost' => $unitCost,
                'movement_value' => $movementValue,
            ];
        }

        $remaining = $qty;
        $movementValue = 0.0;

        foreach ($state['layers'] as $index => $layer) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $layer['qty'];
            if ($available <= 0) {
                continue;
            }

            $consume = min($available, $remaining);
            $movementValue += round($consume * (float) $layer['unit_cost'], 6);
            $state['layers'][$index]['qty'] = round($available - $consume, 6);
            $remaining = round($remaining - $consume, 6);
        }

        $state['layers'] = array_values(array_filter(
            $state['layers'],
            fn (array $layer): bool => (float) $layer['qty'] > 0.000001
        ));

        if ($remaining > 0.000001) {
            $fallbackCost = $this->baseUnitCost($row);
            $movementValue += round($remaining * $fallbackCost, 6);
        }

        $unitCost = $qty > 0 ? round($movementValue / $qty, 6) : 0.0;

        $state['qty'] = round($state['qty'] - $qty, 6);
        $state['value'] = round($this->fifoLayerValue($state), 6);
        $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

        return [
            'unit_cost' => $unitCost,
            'movement_value' => round($movementValue, 6),
        ];
    }

    protected function applyStandard(array &$state, object $row, string $direction, float $qty): array
    {
        $unitCost = $this->standardCost($row);
        $movementValue = round($qty * $unitCost, 6);

        $state['qty'] = $direction === 'out'
            ? round($state['qty'] - $qty, 6)
            : round($state['qty'] + $qty, 6);

        $state['value'] = round($state['qty'] * $unitCost, 6);
        $state['average_cost'] = $unitCost;

        return [
            'unit_cost' => $unitCost,
            'movement_value' => $movementValue,
        ];
    }

    protected function applyRecorded(array &$state, object $row, string $direction, float $qty): array
    {
        $movementValue = $this->recordedLineValue($row, $qty);
        $unitCost = $qty > 0 ? round($movementValue / $qty, 6) : $this->baseUnitCost($row);

        $state['qty'] = $direction === 'out'
            ? round($state['qty'] - $qty, 6)
            : round($state['qty'] + $qty, 6);

        $state['value'] = $direction === 'out'
            ? round($state['value'] - $movementValue, 6)
            : round($state['value'] + $movementValue, 6);

        $state['average_cost'] = $state['qty'] > 0 ? round($state['value'] / $state['qty'], 6) : 0.0;

        return [
            'unit_cost' => $unitCost,
            'movement_value' => round($movementValue, 6),
        ];
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['company_id'])) {
            $query->where('m.company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $warehouseId = (int) $filters['warehouse_id'];

            $query->where(function ($q) use ($warehouseId): void {
                $q->where('m.warehouse_id', $warehouseId)
                    ->orWhere('m.destination_warehouse_id', $warehouseId);
            });
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];
            $query->where(function ($q) use ($locationId): void {
                $q->where('m.source_location_id', $locationId)
                    ->orWhere('m.destination_location_id', $locationId);
            });
        }

        if (! empty($filters['product_search']) && empty($filters['product_id'])) {
            $search = trim((string) $filters['product_search']);
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('p.name', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference', 'variant_name'] as $column) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('products', $column)) {
                        $q->orWhere("p.{$column}", 'ilike', $like);
                    }
                }
            });
        }

        if (! empty($filters['product_id'])) {
            $query->where('l.product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('l.product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['lot_id'])) {
            $query->where('l.lot_id', (int) $filters['lot_id']);
        }

        if (! empty($filters['stock_serial_number_id'])) {
            $query->where('l.stock_serial_number_id', (int) $filters['stock_serial_number_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereRaw('coalesce(m.movement_at, m.created_at, l.created_at) >= ?', [$filters['date_from'] . ' 00:00:00']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereRaw('coalesce(m.movement_at, m.created_at, l.created_at) <= ?', [$filters['date_to'] . ' 23:59:59']);
        }

        if (! empty($filters['status'])) {
            $query->where('m.status', (string) $filters['status']);
        }
    }

    protected function direction(
        object $row,
        ?int $warehouseFilter = null,
        ?int $locationFilter = null
    ): string
    {
        /*
         * INV11 FIX1E:
         * La cabecera del movimiento puede señalar un almacén
         * administrativo distinto del almacén donde cambió el stock.
         * Para el saldo total usamos las ubicaciones reales.
         *
         * Esto evita contar como entrada al almacén de origen
         * un movimiento desde una ubicación virtual externa.
         */
        if ($warehouseFilter !== null && $locationFilter === null
            && ! empty($row->source_location_id)
            && ! empty($row->destination_location_id)) {

            $sourceWarehouse = (int) (
                $row->source_location_warehouse_id ?? 0
            );
            $destinationWarehouse = (int) (
                $row->destination_location_warehouse_id ?? 0
            );

            $sourceInside = $sourceWarehouse === $warehouseFilter;
            $destinationInside =
                $destinationWarehouse === $warehouseFilter;

            if ($sourceInside && $destinationInside) {
                if (
                    (int) $row->source_location_id
                    !== (int) $row->destination_location_id
                ) {
                    return 'transfer';
                }

                // Ajuste en una misma ubicación:
                // conservar la interpretación por cantidad y origen.
            } elseif ($sourceInside && ! $destinationInside) {
                return 'out';
            } elseif (! $sourceInside && $destinationInside) {
                return 'in';
            } elseif (
                $sourceWarehouse > 0
                || $destinationWarehouse > 0
                || ! empty($row->source_location_id)
            ) {
                // El movimiento no afecta existencias de este
                // almacén; mantenerlo neutro en el cálculo.
                return 'transfer';
            }
        }

        $sameWarehouseTransfer =
            ! empty($row->warehouse_id)
            && ! empty($row->destination_warehouse_id)
            && (int) $row->warehouse_id === (int) $row->destination_warehouse_id
            && ! empty($row->source_location_id)
            && ! empty($row->destination_location_id)
            && (int) $row->source_location_id !== (int) $row->destination_location_id;

        if ($sameWarehouseTransfer) {
            if ($locationFilter !== null) {
                if ((int) $row->source_location_id === $locationFilter) {
                    return 'out';
                }

                if ((int) $row->destination_location_id === $locationFilter) {
                    return 'in';
                }
            }

            return 'transfer';
        }

        if (
            $warehouseFilter !== null
            && ! empty($row->destination_warehouse_id)
            && (int) $row->destination_warehouse_id !== (int) $row->warehouse_id
        ) {
            if ((int) $row->destination_warehouse_id === $warehouseFilter) {
                return 'in';
            }

            if ((int) $row->warehouse_id === $warehouseFilter) {
                return 'out';
            }
        }

        $sourceType = strtolower((string) ($row->source_type ?? ''));
        $sourceLineType = strtolower((string) ($row->source_line_type ?? ''));
        $reference = strtoupper((string) ($row->reference ?? ''));
        $origin = strtolower((string) ($row->origin_document ?? ''));
        $qty = (float) ($row->done_quantity ?? $row->requested_quantity ?? 0);

        if ($qty < 0) {
            return 'out';
        }

        if (in_array($sourceType, ['sale_delivery', 'pos_order'], true)) {
            return 'out';
        }

        if (in_array($sourceType, ['purchase_receipt', 'pos_order_refund'], true)) {
            return 'in';
        }

        if ($sourceType === 'stock_adjustment') {
            return $qty < 0 ? 'out' : 'in';
        }

        if (str_contains($reference, '/OUT/') || str_contains($reference, '/PDV/') || str_contains($origin, 'sale_delivery')) {
            return 'out';
        }

        if (str_contains($reference, '/IN/') || str_contains($origin, 'purchase_receipt')) {
            return 'in';
        }

        if (str_contains($sourceLineType, 'refund')) {
            return 'in';
        }

        return 'in';
    }

    protected function effectiveCostingMethod(object $row): string
    {
        foreach ([
            $row->variant_costing_method ?? null,
            $row->product_costing_method ?? null,
            $row->category_costing_method ?? null,
            $row->company_default_costing_method ?? null,
        ] as $method) {
            $normalized = $this->normalizeMethod((string) $method);

            if (! in_array($normalized, [self::METHOD_AUTO, 'inherit', ''], true)) {
                return $normalized;
            }
        }

        return self::METHOD_AVERAGE;
    }

    protected function normalizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match ($method) {
            'peps', 'fifo' => self::METHOD_FIFO,
            'standard', 'estandar', 'estándar' => self::METHOD_STANDARD,
            'recorded', 'registrado' => self::METHOD_RECORDED,
            'average', 'promedio' => self::METHOD_AVERAGE,
            'auto', 'inherit', '' => self::METHOD_AUTO,
            default => self::METHOD_AVERAGE,
        };
    }

    protected function balanceKey(object $row): string
    {
        return implode('|', [
            (int) ($row->company_id ?? $row->product_company_id ?? 0),
            (int) ($row->effective_warehouse_id ?? $row->warehouse_id ?? 0),
            (int) ($row->product_id ?? 0),
            (int) ($row->product_variant_id ?? 0),
            (int) ($row->lot_id ?? 0),
            (int) ($row->stock_serial_number_id ?? 0),
        ]);
    }

    protected function initialState(object $row): array
    {
        return [
            'qty' => 0.0,
            'value' => 0.0,
            'average_cost' => 0.0,
            'layers' => [],
            'standard_cost' => $this->standardCost($row),
        ];
    }

    protected function recordedLineValue(object $row, float $qty): float
    {
        if ($row->total_cost !== null) {
            return round(abs((float) $row->total_cost), 6);
        }

        return round($qty * $this->baseUnitCost($row), 6);
    }

    protected function baseUnitCost(object $row): float
    {
        $qty = abs((float) ($row->done_quantity ?? $row->requested_quantity ?? 0));

        if ($qty > 0 && $row->total_cost !== null) {
            return round(abs((float) $row->total_cost) / $qty, 6);
        }

        return round((float) ($row->unit_cost ?? 0), 6);
    }

    protected function standardCost(object $row): float
    {
        foreach ([
            $row->variant_standard_cost ?? null,
            $row->product_standard_cost ?? null,
            $row->variant_average_cost_without_tax ?? null,
            $row->product_average_cost_without_tax ?? null,
            $row->variant_last_purchase_cost ?? null,
            $row->product_last_purchase_cost ?? null,
            $row->unit_cost ?? null,
        ] as $cost) {
            if ($cost !== null && (float) $cost > 0) {
                return round((float) $cost, 6);
            }
        }

        return 0.0;
    }

    protected function fifoLayerValue(array $state): float
    {
        $value = 0.0;

        foreach ($state['layers'] as $layer) {
            $value += round((float) $layer['qty'] * (float) $layer['unit_cost'], 6);
        }

        return round($value, 6);
    }
}
