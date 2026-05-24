<?php

namespace App\Support\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductTraceabilityService
{
    public function rows(array $filters = []): Collection
    {
        if (! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_movements')) {
            return collect();
        }

        $query = DB::table('stock_movement_lines as l')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->leftJoin('stock_operation_types as ot', 'ot.id', '=', 'm.stock_operation_type_id')
            ->leftJoin('companies as c', 'c.id', '=', 'm.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->leftJoin('stock_locations as src', 'src.id', '=', 'm.source_location_id')
            ->leftJoin('stock_locations as dst', 'dst.id', '=', 'm.destination_location_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'l.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->leftJoin('stock_serial_numbers as sn', 'sn.id', '=', 'l.stock_serial_number_id')
            ->select([
                'l.id as line_id',
                'l.stock_movement_id',
                'm.reference',
                'm.origin_document',
                'm.status',
                'm.company_id',
                'c.name as company_name',
                'm.warehouse_id',
                'w.name as warehouse_name',
                'm.source_location_id',
                'src.name as source_location_name',
                'm.destination_location_id',
                'dst.name as destination_location_name',
                'm.movement_at',
                'm.created_at as movement_created_at',
                'm.created_by',
                'm.confirmed_by',
                'm.confirmed_at',
                'ot.id as operation_type_id',
                'ot.code as operation_code',
                'ot.name as operation_name',
                'ot.operation_kind',
                'l.product_id',
                'p.name as product_name',
                'l.product_variant_id',
                'v.name as variant_name',
                'l.lot_id',
                'lot.lot_number',
                'l.stock_serial_number_id',
                'sn.serial_number',
                'sn.status as serial_status',
                'sn.current_warehouse_id as serial_current_warehouse_id',
                'sn.current_location_id as serial_current_location_id',
                'l.requested_quantity',
                'l.done_quantity',
                'l.unit_cost',
                'l.total_cost',
                'l.costing_method',
                'l.cost_source',
                'l.source_type',
                'l.source_id',
                'l.source_line_type',
                'l.source_line_id',
                'l.notes',
            ]);

        if (! empty($filters['company_id'])) {
            $query->where('m.company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('m.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['location_id'])) {
            $locationId = (int) $filters['location_id'];

            $query->where(function ($q) use ($locationId): void {
                $q->where('m.source_location_id', $locationId)
                    ->orWhere('m.destination_location_id', $locationId);
            });
        }

        if (! empty($filters['product_id'])) {
            $query->where('l.product_id', (int) $filters['product_id']);
        } elseif (! empty($filters['product_search'])) {
            $search = trim((string) $filters['product_search']);
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('p.name', 'ilike', $like)
                    ->orWhere('v.name', 'ilike', $like)
                    ->orWhere('l.notes', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhere("p.{$column}", 'ilike', $like)
                            ->orWhere("v.{$column}", 'ilike', $like);
                    }
                }
            });
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

        if (! empty($filters['operation_kind'])) {
            $query->where('ot.operation_kind', (string) $filters['operation_kind']);
        }

        if (! empty($filters['source_group'])) {
            $sourceGroup = (string) $filters['source_group'];

            $query->where(function ($q) use ($sourceGroup): void {
                if ($sourceGroup === 'purchase_receipt') {
                    $q->where('l.source_type', 'purchase_receipt')
                        ->orWhere('m.origin_document', 'like', 'purchase_receipt:%')
                        ->orWhere('m.reference', 'like', '%/IN/%');
                } elseif ($sourceGroup === 'sale_delivery') {
                    $q->where('l.source_type', 'sale_delivery')
                        ->orWhere('m.origin_document', 'like', 'sale_delivery:%')
                        ->orWhere('m.reference', 'like', '%/OUT/%');
                } elseif ($sourceGroup === 'pos_order') {
                    $q->where('l.source_type', 'pos_order')
                        ->orWhere('m.origin_document', 'like', 'pos_order:%')
                        ->orWhere('m.origin_document', 'like', 'PDV%')
                        ->orWhere('m.reference', 'like', '%/PDV/%');
                } elseif ($sourceGroup === 'pos_refund') {
                    $q->where('l.source_type', 'pos_order_refund')
                        ->orWhere('m.origin_document', 'like', 'DEV-%')
                        ->orWhere('m.reference', 'like', '%/DEV/%');
                } elseif ($sourceGroup === 'internal_transfer') {
                    $q->where('ot.operation_kind', 'internal_transfer')
                        ->orWhere('m.reference', 'like', '%/INT/%');
                } elseif ($sourceGroup === 'legacy') {
                    $q->whereNull('l.source_type');
                }
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate(DB::raw('coalesce(m.movement_at, m.created_at)'), '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate(DB::raw('coalesce(m.movement_at, m.created_at)'), '<=', (string) $filters['date_to']);
        }

        $limit = min(5000, max(50, (int) ($filters['limit'] ?? 500)));

        return $query
            ->orderByDesc(DB::raw('coalesce(m.movement_at, m.created_at)'))
            ->orderByDesc('l.id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->decorate($row));
    }

    public function summary(array $filters = []): array
    {
        $rows = $this->rows($filters);

        return [
            'lines' => $rows->count(),
            'in_qty' => round((float) $rows->where('direction', 'in')->sum('quantity_abs'), 6),
            'out_qty' => round((float) $rows->where('direction', 'out')->sum('quantity_abs'), 6),
            'net_qty' => round((float) $rows->sum('signed_quantity'), 6),
            'with_lot' => $rows->filter(fn ($row) => ! empty($row->lot_id))->count(),
            'with_serial' => $rows->filter(fn ($row) => ! empty($row->stock_serial_number_id))->count(),
            'legacy_origin' => $rows->filter(fn ($row) => empty($row->source_type))->count(),
        ];
    }

    protected function decorate(object $row): object
    {
        $direction = $this->direction($row);
        $qty = abs((float) ($row->done_quantity ?? 0));
        $signedQty = $direction === 'out' ? -1 * $qty : $qty;

        $row->quantity_abs = $qty;
        $row->signed_quantity = $signedQty;
        $row->direction = $direction;
        $row->direction_label = $direction === 'out' ? 'Salida' : 'Entrada';
        $row->origin_label = $this->originLabel($row);
        $row->document_label = $this->documentLabel($row);
        $row->operation_label = $row->operation_name ?: $this->fallbackOperationLabel($row);
        $row->date_label = $row->movement_at ?: $row->movement_created_at;
        $row->location_flow = trim(($row->source_location_name ?: '—') . ' → ' . ($row->destination_location_name ?: '—'));
        $row->legacy_label = empty($row->source_type) ? 'Origen histórico / sin enlace directo' : 'Origen enlazado';
        $row->source_reference_label = $this->sourceReferenceLabel($row);
        $row->costing_method_label = $this->costingMethodLabel($row->costing_method ?? null);
        $row->cost_source_label = $this->costSourceLabel($row->cost_source ?? null);

        return $row;
    }

    protected function direction(object $row): string
    {
        $kind = strtolower((string) ($row->operation_kind ?? ''));
        $sourceType = strtolower((string) ($row->source_type ?? ''));
        $origin = strtolower((string) ($row->origin_document ?? ''));
        $reference = strtolower((string) ($row->reference ?? ''));

        if (str_contains($kind, 'delivery') || str_contains($sourceType, 'sale') || str_contains($sourceType, 'pos_order')) {
            return 'out';
        }

        if (str_contains($origin, 'pos_order:') || str_contains($reference, '/pdv/') || str_contains($reference, '/out/')) {
            return 'out';
        }

        return 'in';
    }

    protected function originLabel(object $row): string
    {
        $sourceType = strtolower((string) ($row->source_type ?? ''));
        $origin = strtolower((string) ($row->origin_document ?? ''));
        $reference = strtolower((string) ($row->reference ?? ''));
        $operation = strtolower((string) ($row->operation_name ?? ''));

        return match (true) {
            $sourceType === 'purchase_receipt' || str_starts_with($origin, 'purchase_receipt:') || str_contains($reference, '/in/') => 'Compra / recepción',
            $sourceType === 'sale_delivery' || str_starts_with($origin, 'sale_delivery:') || str_contains($reference, '/out/') => 'Entrega de venta',
            $sourceType === 'pos_order' || str_starts_with($origin, 'pos_order:') || str_starts_with($origin, 'pdv') || str_contains($reference, '/pdv/') => 'Venta PDV',
            $sourceType === 'pos_order_refund' || str_starts_with($origin, 'dev-') || str_contains($reference, '/dev/') || str_contains($operation, 'devolución') => 'Devolución PDV',
            str_contains($operation, 'traslado') || str_contains($reference, '/int/') => 'Traslado interno',
            str_contains($operation, 'ajuste') || str_contains($reference, '/aju/') => 'Ajuste de inventario',
            default => $row->operation_name ?: 'Movimiento de inventario',
        };
    }

    protected function sourceReferenceLabel(object $row): string
    {
        $sourceType = strtolower((string) ($row->source_type ?? ''));
        $sourceId = $row->source_id ?? null;

        if ($sourceType === '' || ! $sourceId) {
            return '';
        }

        return match ($sourceType) {
            'purchase_receipt' => 'Recepción de compra #' . $sourceId,
            'sale_delivery' => 'Entrega de venta #' . $sourceId,
            'pos_order' => 'Ticket PDV #' . $sourceId,
            'pos_order_refund' => 'Devolución PDV #' . $sourceId,
            'stock_adjustment' => 'Ajuste de inventario #' . $sourceId,
            default => 'Documento enlazado #' . $sourceId,
        };
    }

    protected function costingMethodLabel(?string $method): string
    {
        $method = strtolower(trim((string) $method));

        return match ($method) {
            'average' => 'Costo promedio',
            'fifo' => 'PEPS / FIFO',
            'standard' => 'Costo estándar',
            'manual' => 'Costo manual',
            '' => 'Sin método',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    protected function costSourceLabel(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        if ($source === '') {
            return 'Sin fuente de costo';
        }

        return match (true) {
            str_contains($source, 'purchase_receipt') => 'Costo de recepción de compra',
            str_contains($source, 'sale_delivery') => 'Costo de entrega de venta',
            str_contains($source, 'pos_order.average_cost_at_sale') => 'Costo promedio al vender en PDV',
            str_contains($source, 'pos_order_refund.original_sale_cost') => 'Costo original de venta devuelta',
            str_contains($source, 'stock_adjustment') => 'Costo de ajuste de inventario',
            str_contains($source, 'legacy.stock_movement_line.unit_cost:product_variant') => 'Costo histórico registrado en variante',
            str_contains($source, 'legacy.stock_movement_line.unit_cost:product') => 'Costo histórico registrado en producto',
            str_contains($source, 'legacy') => 'Costo histórico registrado',
            str_contains($source, 'product_variant') => 'Costo de la variante',
            str_contains($source, 'product') => 'Costo del producto',
            default => ucfirst(str_replace(['_', '.'], [' ', ' / '], $source)),
        };
    }
    protected function documentLabel(object $row): string
    {
        $origin = (string) ($row->origin_document ?? '');

        if ($origin !== '') {
            if (str_starts_with($origin, 'purchase_receipt:')) {
                return 'Recepción ' . substr($origin, strlen('purchase_receipt:'));
            }

            if (str_starts_with($origin, 'sale_delivery:')) {
                return 'Entrega de venta #' . substr($origin, strlen('sale_delivery:'));
            }

            if (str_starts_with($origin, 'pos_order:')) {
                return 'Ticket PDV #' . substr($origin, strlen('pos_order:'));
            }

            if (str_starts_with(strtoupper($origin), 'PDV')) {
                return 'Ticket PDV ' . $origin;
            }

            if (str_starts_with(strtoupper($origin), 'DEV-')) {
                return 'Devolución PDV ' . $origin;
            }

            return $origin;
        }

        if (! empty($row->source_type) && ! empty($row->source_id)) {
            return $this->sourceReferenceLabel($row);
        }

        return (string) ($row->reference ?: ('Movimiento #' . $row->stock_movement_id));
    }

    protected function fallbackOperationLabel(object $row): string
    {
        $reference = strtoupper((string) ($row->reference ?? ''));

        return match (true) {
            str_contains($reference, '/IN/') => 'Entrada por compra',
            str_contains($reference, '/OUT/') => 'Salida por venta',
            str_contains($reference, '/PDV/') => 'Venta PDV',
            str_contains($reference, '/DEV/') => 'Entrada por devolución',
            str_contains($reference, '/INT/') => 'Traslado',
            str_contains($reference, '/AJU/') => 'Ajuste de inventario',
            default => 'Movimiento de inventario',
        };
    }
}
