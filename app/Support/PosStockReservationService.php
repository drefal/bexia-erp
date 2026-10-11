<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosStockReservationService
{
    public function reserveOrder(int $orderId, ?int $userId = null): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Ticket inválido.'];
        }

        if (
            ! Schema::hasTable('stock_reservations')
            || ! Schema::hasTable('stock_quants')
            || ! Schema::hasTable('pos_orders')
            || ! Schema::hasTable('pos_order_lines')
            || ! Schema::hasTable('products')
            || ! Schema::hasTable('pos_points')
        ) {
            return ['ok' => false, 'message' => 'Faltan tablas para reservar inventario.'];
        }

        return DB::transaction(function () use ($orderId, $userId): array {
            $order = DB::table('pos_orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return ['ok' => false, 'message' => 'No se encontró el ticket.'];
            }

            if ((string) ($order->status ?? '') !== 'pending_payment') {
                return ['ok' => true, 'message' => 'El ticket no está pendiente; no requiere reserva.'];
            }

            $pos = null;

            if (! empty($order->pos_point_id)) {
                $pos = DB::table('pos_points')
                    ->where('id', (int) $order->pos_point_id)
                    ->first();
            }

            $companyId = (int) ($order->company_id ?? $pos->company_id ?? 0);
            $warehouseId = (int) ($pos->warehouse_id ?? 0);
            $locationId = (int) ($pos->stock_source_location_id ?? $pos->stock_location_id ?? 0);

            if ($companyId <= 0 || $warehouseId <= 0 || $locationId <= 0) {
                return ['ok' => false, 'message' => 'Falta configurar empresa, almacén o ubicación del PDV.'];
            }

            /*
             * BEXIA_POS53C2M5E_STOCK_GUARD
             *
             * Validar las cantidades agrupadas y bloquear quants
             * antes de reemplazar las reservas del ticket.
             *
             * Las reservas del propio ticket pueden reemplazarse,
             * pero las de otros tickets deben permanecer cubiertas.
             */
            $pos53RequestedLines = DB::table('pos_order_lines as l')
                ->join('products as p', 'p.id', '=', 'l.product_id')
                ->where('l.pos_order_id', $orderId)
                ->where('p.product_type', 'stockable')
                ->select([
                    'l.product_id',
                    'l.product_variant_id',
                    'l.stock_lot_id',
                    'l.quantity',
                ])
                ->get();

            $pos53Requested = [];

            foreach ($pos53RequestedLines as $pos53Line) {
                $pos53ProductId = (int) $pos53Line->product_id;
                $pos53VariantId = (int) ($pos53Line->product_variant_id ?? 0);
                $pos53LotId = (int) ($pos53Line->stock_lot_id ?? 0);
                $pos53Qty = round((float) $pos53Line->quantity, 6);

                if ($pos53ProductId <= 0 || $pos53Qty <= 0) {
                    return [
                        'ok' => false,
                        'message' => 'Una linea inventariable tiene cantidad invalida.',
                    ];
                }

                $pos53Key = implode(':', [
                    $pos53ProductId,
                    $pos53VariantId,
                    $pos53LotId,
                ]);

                if (! isset($pos53Requested[$pos53Key])) {
                    $pos53Requested[$pos53Key] = [
                        'product_id' => $pos53ProductId,
                        'variant_id' => $pos53VariantId,
                        'lot_id' => $pos53LotId,
                        'quantity' => 0.0,
                    ];
                }

                $pos53Requested[$pos53Key]['quantity'] = round(
                    $pos53Requested[$pos53Key]['quantity'] + $pos53Qty,
                    6
                );
            }

            ksort($pos53Requested, SORT_STRING);

            foreach ($pos53Requested as $pos53Item) {
                $pos53QuantQuery = DB::table('stock_quants')
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('location_id', $locationId)
                    ->where('product_id', $pos53Item['product_id']);

                $pos53ReservedQuery = DB::table('stock_reservations')
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('location_id', $locationId)
                    ->where('product_id', $pos53Item['product_id'])
                    ->where('status', 'active');

                foreach ([
                    ['product_variant_id', 'variant_id'],
                    ['lot_id', 'lot_id'],
                ] as $pos53Dimension) {
                    [$pos53Column, $pos53Field] = $pos53Dimension;
                    $pos53Value = (int) $pos53Item[$pos53Field];

                    if ($pos53Value > 0) {
                        $pos53QuantQuery->where($pos53Column, $pos53Value);
                        $pos53ReservedQuery->where($pos53Column, $pos53Value);
                    } else {
                        $pos53QuantQuery->whereNull($pos53Column);
                        $pos53ReservedQuery->whereNull($pos53Column);
                    }
                }

                $pos53Quants = $pos53QuantQuery
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $pos53Physical = round(
                    (float) $pos53Quants->sum('quantity'),
                    6
                );

                $pos53ActiveReserved = round(
                    (float) (clone $pos53ReservedQuery)->sum('quantity'),
                    6
                );

                $pos53OwnReserved = round(
                    (float) (clone $pos53ReservedQuery)
                        ->where('source_type', 'pos_order')
                        ->where('source_id', $orderId)
                        ->sum('quantity'),
                    6
                );

                $pos53ReservedByOthers = max(
                    0,
                    round($pos53ActiveReserved - $pos53OwnReserved, 6)
                );

                $pos53AvailableForTicket = round(
                    $pos53Physical - $pos53ReservedByOthers,
                    6
                );

                if (
                    $pos53Quants->isEmpty()
                    || $pos53Item['quantity'] > $pos53AvailableForTicket + 0.000001
                ) {
                    return [
                        'ok' => false,
                        'message' =>
                            'Existencias insuficientes para reservar producto #'
                            . $pos53Item['product_id']
                            . '. Disponible para este ticket: '
                            . number_format(
                                max(0, $pos53AvailableForTicket),
                                2,
                                '.',
                                ''
                            )
                            . '. Solicitado: '
                            . number_format(
                                $pos53Item['quantity'],
                                2,
                                '.',
                                ''
                            ),
                    ];
                }
            }

            $this->releaseOrder($orderId, 'replaced');

            $lines = DB::table('pos_order_lines as l')
                ->join('products as p', 'p.id', '=', 'l.product_id')
                ->where('l.pos_order_id', $orderId)
                ->where('p.product_type', 'stockable')
                ->select([
                    'l.id as line_id',
                    'l.product_id',
                    'l.product_variant_id',
                    'l.stock_lot_id',
                    'l.quantity',
                    'p.product_type',
                    'p.name as product_name',
                ])
                ->get();

            $inserted = 0;
            $affected = collect();

            foreach ($lines as $line) {
                $qty = round((float) ($line->quantity ?? 0), 6);

                if ($qty <= 0 || empty($line->product_id)) {
                    continue;
                }

                /*
                 * BEXIA_V5836G5B_RESERVE_VARIANT_AND_LOT
                 *
                 * La reserva debe usar las mismas dimensiones de inventario
                 * que la linea del ticket. De lo contrario una variante
                 * queda reservada sobre el producto padre y no disminuye
                 * correctamente el disponible de esa variante.
                 */
                $variantId = ! empty($line->product_variant_id)
                    ? (int) $line->product_variant_id
                    : null;

                $lotId = ! empty($line->stock_lot_id)
                    ? (int) $line->stock_lot_id
                    : null;

                DB::table('stock_reservations')->insert([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'product_id' => (int) $line->product_id,
                    'product_variant_id' => $variantId,
                    'lot_id' => $lotId,
                    'source_type' => 'pos_order',
                    'source_id' => $orderId,
                    'pos_order_id' => $orderId,
                    'pos_order_line_id' => (int) $line->line_id,
                    'quantity' => $qty,
                    'status' => 'active',
                    'reserved_at' => now(),
                    'released_at' => null,
                    'released_reason' => null,
                    'created_by' => $userId ?: auth()->id(),
                    'metadata' => json_encode([
                        'source' => 'pdv_pending_ticket',
                        'order_number' => $order->number ?? null,
                        'product_name' => $line->product_name ?? null,
                        'product_variant_id' => $variantId,
                        'stock_lot_id' => $lotId,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inserted++;

                $affected->push([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'product_id' => (int) $line->product_id,
                    'product_variant_id' => $variantId,
                    'lot_id' => $lotId,
                ]);
            }

            $this->syncAffected($affected);

            return [
                'ok' => true,
                'reserved_lines' => $inserted,
                'message' => 'Reserva de inventario generada.',
            ];
        });
    }

    public function releaseOrder(int $orderId, string $reason = 'released'): array
    {
        if (
            $orderId <= 0
            || ! Schema::hasTable('stock_reservations')
            || ! Schema::hasTable('stock_quants')
        ) {
            return ['ok' => false, 'message' => 'No se pudo liberar reserva.'];
        }

        return DB::transaction(function () use ($orderId, $reason): array {
            $reservations = DB::table('stock_reservations')
                ->where('source_type', 'pos_order')
                ->where('source_id', $orderId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return ['ok' => true, 'released_lines' => 0, 'message' => 'No había reservas activas.'];
            }

            $affected = $reservations->map(fn ($row): array => [
                'company_id' => (int) ($row->company_id ?? 0),
                'warehouse_id' => (int) ($row->warehouse_id ?? 0),
                'location_id' => (int) ($row->location_id ?? 0),
                'product_id' => (int) ($row->product_id ?? 0),
                'product_variant_id' => $row->product_variant_id ? (int) $row->product_variant_id : null,
                'lot_id' => $row->lot_id ? (int) $row->lot_id : null,
            ]);

            DB::table('stock_reservations')
                ->whereIn('id', $reservations->pluck('id')->all())
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'released_reason' => $reason,
                    'updated_at' => now(),
                ]);

            $this->syncAffected($affected);

            return [
                'ok' => true,
                'released_lines' => $reservations->count(),
                'message' => 'Reserva liberada.',
            ];
        });
    }

    public function backfillPendingOrders(?int $companyId = null): array
    {
        if (! Schema::hasTable('pos_orders')) {
            return ['ok' => false, 'message' => 'No existe pos_orders.'];
        }

        $query = DB::table('pos_orders')
            ->where('status', 'pending_payment')
            ->orderBy('id');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $orders = $query->pluck('id');

        $processed = 0;
        $reservedLines = 0;

        foreach ($orders as $orderId) {
            $result = $this->reserveOrder((int) $orderId);
            $processed++;

            if (! empty($result['reserved_lines'])) {
                $reservedLines += (int) $result['reserved_lines'];
            }
        }

        return [
            'ok' => true,
            'orders_processed' => $processed,
            'reserved_lines' => $reservedLines,
            'message' => 'Backfill de reservas completado.',
        ];
    }

    protected function syncAffected(Collection $affected): void
    {
        $affected
            ->filter(fn ($row) => ! empty($row['product_id']))
            ->unique(fn ($row) => implode('|', [
                $row['company_id'] ?? 0,
                $row['warehouse_id'] ?? 0,
                $row['location_id'] ?? 0,
                $row['product_id'] ?? 0,
                $row['product_variant_id'] ?? 0,
                $row['lot_id'] ?? 0,
            ]))
            ->each(function (array $row): void {
                $this->syncQuantReservedQuantity(
                    (int) ($row['company_id'] ?? 0),
                    (int) ($row['warehouse_id'] ?? 0),
                    (int) ($row['location_id'] ?? 0),
                    (int) ($row['product_id'] ?? 0),
                    $row['product_variant_id'] ?? null,
                    $row['lot_id'] ?? null,
                );
            });
    }

    public function syncQuantReservedQuantity(
        int $companyId,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?int $productVariantId = null,
        ?int $lotId = null
    ): void {
        if (
            ! Schema::hasTable('stock_reservations')
            || ! Schema::hasTable('stock_quants')
            || $companyId <= 0
            || $warehouseId <= 0
            || $locationId <= 0
            || $productId <= 0
        ) {
            return;
        }

        $reservedQuery = DB::table('stock_reservations')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->where('status', 'active');

        $productVariantId
            ? $reservedQuery->where('product_variant_id', $productVariantId)
            : $reservedQuery->whereNull('product_variant_id');

        $lotId
            ? $reservedQuery->where('lot_id', $lotId)
            : $reservedQuery->whereNull('lot_id');

        $reserved = round((float) $reservedQuery->sum('quantity'), 6);

        $quantQuery = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        $productVariantId
            ? $quantQuery->where('product_variant_id', $productVariantId)
            : $quantQuery->whereNull('product_variant_id');

        $lotId
            ? $quantQuery->where('lot_id', $lotId)
            : $quantQuery->whereNull('lot_id');

        $quant = $quantQuery->lockForUpdate()->first();

        if ($quant) {
            DB::table('stock_quants')
                ->where('id', $quant->id)
                ->update([
                    'reserved_quantity' => $reserved,
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($reserved > 0) {
            DB::table('stock_quants')->insert([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'lot_id' => $lotId,
                'quantity' => 0,
                'reserved_quantity' => $reserved,
                'average_cost' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
