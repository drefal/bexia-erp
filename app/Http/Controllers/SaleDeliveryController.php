<?php

namespace App\Http\Controllers;

use App\Support\Inventory\OutboundSerialNumberService;
use App\Models\SaleDelivery;
use App\Models\SaleOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaleDeliveryController extends Controller
{
    public function show(SaleOrder $saleOrder)
    {
        if (! $this->userCanUpdateSales()) {
            abort(403);
        }

        if (! in_array((string) $saleOrder->status, ['confirmed', 'partially_delivered'], true)) {
            $message = (string) $saleOrder->status === 'delivered'
                ? 'La orden ya fue entregada completa. No tiene cantidades pendientes para entregar.'
                : 'Solo las órdenes confirmadas o parcialmente entregadas pueden crear entregas.';

            return redirect('/admin/' . (int) $saleOrder->company_id . '/sale-orders/' . (int) $saleOrder->id . '/edit')
                ->with('warning', $message);
        }

        return view('filament.sales-orders.delivery-standalone', [
            'saleOrder' => $saleOrder,
            'saleOrderId' => $saleOrder->id,
        ]);
    }

    public function storeFull(Request $request, SaleOrder $saleOrder): RedirectResponse
    {
        return $this->createDelivery($request, $saleOrder, 'complete');
    }

    public function storePartial(Request $request, SaleOrder $saleOrder): RedirectResponse
    {
        return $this->createDelivery($request, $saleOrder, 'auto');
    }

    public function showDelivery(SaleDelivery $saleDelivery)
    {
        if (! $this->userCanUpdateSales()) {
            abort(403);
        }

        return view('filament.sales-deliveries.show', [
            'delivery' => $saleDelivery,
        ]);
    }

    public function printDelivery(SaleDelivery $saleDelivery)
    {
        if (! $this->userCanUpdateSales()) {
            abort(403);
        }

        return view('filament.sales-deliveries.print', [
            'delivery' => $saleDelivery,
        ]);
    }

    public function validateDelivery(Request $request, SaleDelivery $saleDelivery): RedirectResponse
    {
        if (! $this->userCanUpdateSales()) {
            abort(403);
        }

        if ((string) $saleDelivery->status !== 'draft') {
            return back()->with('error', 'Solo se pueden validar entregas en borrador.');
        }

        if (! empty($saleDelivery->stock_movement_id)) {
            return back()->with('error', 'Esta entrega ya tiene movimiento de inventario.');
        }

        if (! Schema::hasTable('stock_quants') || ! Schema::hasTable('stock_movements') || ! Schema::hasTable('stock_movement_lines')) {
            return back()->with('error', 'Faltan tablas de inventario para validar la entrega.');
        }

        $lines = DB::table('sale_delivery_lines')
            ->where('sale_delivery_id', $saleDelivery->id)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return back()->with('error', 'La entrega no tiene líneas.');
        }

        $order = DB::table('sales_orders')
            ->where('id', $saleDelivery->sales_order_id)
            ->first();

        if (! $order) {
            return back()->with('error', 'No se encontró la orden de venta relacionada.');
        }

        $serialSelections = $this->normalizeSerialSelections($request->input('serial_numbers', []));
        $lotSelections = $this->normalizeLotSelections($request->input('lot_numbers', []));

        try {
            $movementId = DB::transaction(function () use ($saleDelivery, $lines, $order, $serialSelections, $lotSelections): int {
                $now = now();

                $lockedQuants = [];

                foreach ($lines as $line) {
                    $qty = $this->decimal($line->quantity ?? 0);

                    if ($qty <= 0) {
                        throw new \RuntimeException('La línea ' . ($line->product_label ?? '') . ' no tiene cantidad válida.');
                    }

                    $serialNumberId = $serialSelections[(int) $line->id] ?? null;

                    if ($this->lineRequiresSerialNumber($line) && ! $serialNumberId) {
                        throw new \RuntimeException('Selecciona número de serie para ' . ($line->product_label ?? 'producto') . $this->variantSuffix($line) . '.');
                    }

                    if ($serialNumberId && abs($qty - 1.0) > 0.000001) {
                        throw new \RuntimeException('La línea ' . ($line->product_label ?? 'producto') . $this->variantSuffix($line) . ' usa número de serie y debe entregarse con cantidad 1.');
                    }

                    if ($serialNumberId) {
                        app(OutboundSerialNumberService::class)->assertSerialAvailable(
                            $serialNumberId,
                            $this->serialContextForDeliveryLine($saleDelivery, $line)
                        );
                    }

                    $requestedLotId = $lotSelections[(int) $line->id] ?? null;
                    $lineLotId = $this->selectedLotIdForDeliveryLine($line, $requestedLotId);

                    if ($this->lineRequiresLotNumber($line) && ! $lineLotId) {
                        throw new \RuntimeException('Selecciona lote para ' . ($line->product_label ?? 'producto') . $this->variantSuffix($line) . '.');
                    }

                    $quant = $this->lockQuantForDeliveryLine($saleDelivery, $line, $lineLotId);

                    if (! $quant) {
                        throw new \RuntimeException(
                            'No hay existencia para '
                            . ($line->product_label ?? 'producto')
                            . $this->variantSuffix($line)
                            . ' en '
                            . $this->deliverySourceLabel($saleDelivery)
                            . '. '
                            . $this->availabilityHintForDeliveryLine($saleDelivery, $line, $qty, $lineLotId)
                        );
                    }

                    $lineLotId = $lineLotId ?: (! empty($quant->lot_id) ? (int) $quant->lot_id : null);

                    /*
                     * En borrador la cantidad debe estar reservada.
                     * Para validar, revisamos la existencia física total.
                     */
                    $physical = $this->decimal($quant->quantity ?? 0);

                    if ($physical < $qty) {
                        throw new \RuntimeException(
                            'Existencia insuficiente para '
                            . ($line->product_label ?? 'producto')
                            . $this->variantSuffix($line)
                            . ' en '
                            . $this->deliverySourceLabel($saleDelivery)
                            . '. Existencia: '
                            . number_format($physical, 2)
                            . ', requerido: '
                            . number_format($qty, 2)
                            . '. '
                            . $this->availabilityHintForDeliveryLine($saleDelivery, $line, $qty, $lineLotId)
                        );
                    }

                    $lockedQuants[(int) $line->id] = [
                        'quant' => $quant,
                        'lot_id' => $lineLotId,
                    ];
                }

                $movementData = $this->filterTableColumns('stock_movements', [
                    'company_id' => $saleDelivery->company_id,
                    'warehouse_id' => $saleDelivery->warehouse_id,
                    'stock_operation_type_id' => $this->stockOperationTypeId((int) $saleDelivery->company_id),
                    'source_location_id' => $saleDelivery->source_location_id,
                    'destination_location_id' => $saleDelivery->destination_location_id,
                    'reference' => $saleDelivery->number,
                    'movement_at' => $now,
                    'status' => 'done',
                    'origin_document' => 'sale_delivery:' . $saleDelivery->id,
                    'contact_id' => $order->customer_contact_id ?? null,
                    'notes' => 'Salida por entrega de venta ' . ($saleDelivery->number ?: ('#' . $saleDelivery->id)),
                    'created_by' => auth()->id(),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $movementId = DB::table('stock_movements')->insertGetId($movementData);
                \App\Support\Inventory\StockMovementNormalizer::normalizeMovement((int) $movementId);

                foreach ($lines as $line) {
                    $qty = $this->decimal($line->quantity ?? 0);
                    $lockedQuant = $lockedQuants[(int) $line->id];
                    $quant = is_array($lockedQuant) ? ($lockedQuant['quant'] ?? null) : $lockedQuant;
                    $lineLotId = is_array($lockedQuant) ? ($lockedQuant['lot_id'] ?? null) : (! empty($quant->lot_id) ? (int) $quant->lot_id : null);
                    $serialNumberId = $serialSelections[(int) $line->id] ?? null;

                    if ($lineLotId) {
                        $this->releaseMismatchedLotReservation($saleDelivery, $line, (int) $lineLotId, $qty, $now);
                    }

                    $movementLineData = $this->filterTableColumns('stock_movement_lines', [
                        'stock_movement_id' => $movementId,
                        'product_id' => $line->product_id,
                        'product_variant_id' => $line->product_variant_id,
                        'lot_id' => $lineLotId,
                        'stock_serial_number_id' => $serialNumberId,
                        'source_type' => 'sale_delivery',
                        'source_id' => $saleDelivery->id,
                        'source_line_type' => 'sale_delivery_line',
                        'source_line_id' => $line->id,
                        'requested_quantity' => $qty,
                        'done_quantity' => $qty,
                        'unit_cost' => $line->unit_cost ?? 0,
                        'notes' => $line->product_label,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $movementLineId = DB::table('stock_movement_lines')->insertGetId($movementLineData);

                    if ($serialNumberId) {
                        app(OutboundSerialNumberService::class)->markSold(
                            $serialNumberId,
                            $this->serialContextForDeliveryLine($saleDelivery, $line, (int) $movementLineId)
                        );
                    }

                    /*
                     * Validar entrega:
                     * - quantity baja porque sale inventario.
                     * - reserved_quantity baja porque deja de estar reservado.
                     */
                    $newQuantity = max(0, $this->decimal($quant->quantity ?? 0) - $qty);
                    $newReserved = max(0, $this->decimal($quant->reserved_quantity ?? 0) - $qty);

                    DB::table('stock_quants')
                        ->where('id', $quant->id)
                        ->update($this->filterTableColumns('stock_quants', [
                            'quantity' => $newQuantity,
                            'reserved_quantity' => $newReserved,
                            'updated_at' => $now,
                        ]));

                    DB::table('sale_delivery_lines')
                        ->where('id', $line->id)
                        ->update($this->filterTableColumns('sale_delivery_lines', [
                            'stock_movement_line_id' => $movementLineId,
                            'stock_serial_number_id' => $serialNumberId,
                            'stock_lot_id' => $lineLotId,
                            'lot_tracking_metadata' => $lineLotId ? json_encode($this->lotTrackingContextForDeliveryLine($saleDelivery, $line, (int) $lineLotId, (int) $movementLineId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                            'updated_at' => $now,
                        ]));
                }

                DB::table('sale_deliveries')
                    ->where('id', $saleDelivery->id)
                    ->update($this->filterTableColumns('sale_deliveries', [
                        'status' => 'done',
                        'delivered_at' => $now,
                        'stock_movement_id' => $movementId,
                        'updated_at' => $now,
                    ]));

                $this->refreshSalesOrderDeliveryStatus((int) $saleDelivery->sales_order_id);

                return $movementId;
            });

            $orderStatus = (string) DB::table('sales_orders')
                ->where('id', $saleDelivery->sales_order_id)
                ->value('status');

            $message = 'Entrega validada. Se generó el movimiento de salida #' . $movementId . '.';

            if ($orderStatus === 'delivered') {
                try {
                    $receivableId = app(\App\Support\Cxc\AccountReceivableFromSalesOrderService::class)
                        ->createFromSalesOrder((int) $saleDelivery->sales_order_id, auth()->id());

                    if ($receivableId) {
                        $message .= ' Se generó/actualizó la CxC #' . $receivableId . '.';
                    }
                } catch (\Throwable $e) {
                    report($e);

                    return redirect($this->saleOrderEditUrl($saleDelivery))
                        ->with('warning', $message . ' La orden quedó entregada completa, pero no se pudo generar CxC: ' . $e->getMessage());
                }

                return redirect($this->saleOrderEditUrl($saleDelivery))
                    ->with('success', $message . ' La orden quedó entregada completa.');
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, SaleDelivery $saleDelivery): RedirectResponse
    {
        if (! $this->userCanUpdateSales()) {
            abort(403);
        }

        if ((string) $saleDelivery->status !== 'draft') {
            return back()->with('error', 'Solo se pueden cancelar entregas en borrador.');
        }

        if (! empty($saleDelivery->stock_movement_id)) {
            return back()->with('error', 'Esta entrega ya tiene movimiento de inventario ligado.');
        }

        try {
            DB::transaction(function () use ($saleDelivery): void {
                $this->releaseDeliveryReservation($saleDelivery);

                DB::table('sale_deliveries')
                    ->where('id', $saleDelivery->id)
                    ->update($this->filterTableColumns('sale_deliveries', [
                        'status' => 'cancelled',
                        'cancelled_by_user_id' => auth()->id(),
                        'cancelled_at' => now(),
                        'updated_at' => now(),
                    ]));
            });

            return back()->with('success', 'Entrega cancelada y reserva liberada.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }
    }

    protected function createDelivery(Request $request, SaleOrder $saleOrder, string $mode): RedirectResponse
    {
        if (! $this->userCanUpdateSales()) {
            abort(403);
        }

        if (! Schema::hasTable('sale_deliveries') || ! Schema::hasTable('sale_delivery_lines')) {
            return back()->with('error', 'Faltan las tablas de entregas. Ejecuta migraciones.');
        }

        if (! in_array((string) $saleOrder->status, ['confirmed', 'partially_delivered'], true)) {
            return back()->with('error', 'Solo puedes crear entregas para órdenes confirmadas.');
        }

        $pendingLines = collect($this->pendingLines($saleOrder));
        $rows = [];

        if ($mode === 'complete') {
            foreach ($pendingLines as $line) {
                if ($line['_pending'] > 0) {
                    $rows[] = ['line' => $line, 'quantity' => $line['_pending']];
                }
            }
        } else {
            $input = (array) $request->input('line_quantities', []);

            foreach ($pendingLines as $line) {
                $qty = $this->decimal($input[$line['id']] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                if ($qty > $line['_pending']) {
                    return back()
                        ->withInput()
                        ->with('error', 'La cantidad de ' . $line['product_label'] . ' excede lo pendiente.');
                }

                $rows[] = ['line' => $line, 'quantity' => $qty];
            }
        }

        if (count($rows) === 0) {
            return back()->withInput()->with('error', 'Captura al menos una cantidad para entregar.');
        }

        $deliveryType = $this->detectDeliveryType($pendingLines, $rows);
        $hasPendingAfterDelivery = $deliveryType === 'partial';

        try {
            $deliveryId = DB::transaction(function () use ($saleOrder, $deliveryType, $rows, $request): int {
                $now = now();

                /*
                 * Primero validar que haya disponible no reservado.
                 */
                foreach ($rows as $row) {
                    $this->assertCanReserve($saleOrder, $row['line'], $row['quantity']);
                }

                $deliveryId = DB::table('sale_deliveries')->insertGetId($this->filterTableColumns('sale_deliveries', [
                    'company_id' => $saleOrder->company_id,
                    'sales_order_id' => $saleOrder->id,
                    'number' => $this->nextNumber((int) $saleOrder->company_id),
                    'status' => 'draft',
                    'delivery_type' => $deliveryType,
                    'planned_at' => $now,
                    'warehouse_id' => $saleOrder->warehouse_id,
                    'source_location_id' => $saleOrder->location_id,
                    'destination_location_id' => $this->customerLocationId((int) $saleOrder->company_id),
                    'created_by_user_id' => auth()->id(),
                    'notes' => $request->input('notes'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                $insert = [];

                foreach ($rows as $row) {
                    $line = $row['line'];

                    $insert[] = $this->filterTableColumns('sale_delivery_lines', [
                        'sale_delivery_id' => $deliveryId,
                        'sales_order_id' => $saleOrder->id,
                        'sales_order_line_id' => $line['id'],
                        'company_id' => $saleOrder->company_id,
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'product_label' => $line['product_label'],
                        'variant_label' => $line['variant_label'],
                        'ordered_quantity' => $line['_ordered'],
                        'quantity' => $row['quantity'],
                        'unit_cost' => $line['estimated_unit_cost_without_tax'] ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('sale_delivery_lines')->insert($insert);

                /*
                 * Al crear entrega en borrador, reservar inventario.
                 */
                $delivery = SaleDelivery::query()->findOrFail($deliveryId);
                $this->reserveDelivery($delivery);

                return $deliveryId;
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($hasPendingAfterDelivery) {
            return back()
                ->with('success', 'Entrega parcial creada en borrador #' . $deliveryId . '. Inventario reservado.')
                ->with('warning', 'Quedará una entrega pendiente relacionada a esta venta.');
        }

        return back()->with('success', 'Entrega completa creada en borrador #' . $deliveryId . '. Inventario reservado.');
    }

    protected function reserveDelivery(SaleDelivery $saleDelivery): void
    {
        $lines = DB::table('sale_delivery_lines')
            ->where('sale_delivery_id', $saleDelivery->id)
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $qty = $this->decimal($line->quantity ?? 0);
            $quant = $this->lockQuantForDeliveryLine($saleDelivery, $line);

            if (! $quant) {
                throw new \RuntimeException(
                    'No hay existencia para reservar '
                    . ($line->product_label ?? 'producto')
                    . $this->variantSuffix($line)
                    . ' en '
                    . $this->deliverySourceLabel($saleDelivery)
                    . '. '
                    . $this->availabilityHintForDeliveryLine($saleDelivery, $line, $qty)
                );
            }

            $available = $this->decimal($quant->quantity ?? 0) - $this->decimal($quant->reserved_quantity ?? 0);

            if ($available < $qty) {
                throw new \RuntimeException(
                    'Existencia disponible insuficiente para reservar '
                    . ($line->product_label ?? 'producto')
                    . $this->variantSuffix($line)
                    . ' en '
                    . $this->deliverySourceLabel($saleDelivery)
                    . '. Disponible: '
                    . number_format($available, 2)
                    . ', requerido: '
                    . number_format($qty, 2)
                    . '. '
                    . $this->availabilityHintForDeliveryLine($saleDelivery, $line, $qty)
                );
            }

            DB::table('stock_quants')
                ->where('id', $quant->id)
                ->update($this->filterTableColumns('stock_quants', [
                    'reserved_quantity' => $this->decimal($quant->reserved_quantity ?? 0) + $qty,
                    'updated_at' => now(),
                ]));

            $lotId = ! empty($quant->lot_id) ? (int) $quant->lot_id : null;

            if ($lotId) {
                DB::table('sale_delivery_lines')
                    ->where('id', $line->id)
                    ->update($this->filterTableColumns('sale_delivery_lines', [
                        'stock_lot_id' => $lotId,
                        'lot_tracking_metadata' => json_encode($this->lotTrackingContextForDeliveryLine($saleDelivery, $line, $lotId, null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                    ]));
            }
        }
    }

    protected function releaseDeliveryReservation(SaleDelivery $saleDelivery): void
    {
        $lines = DB::table('sale_delivery_lines')
            ->where('sale_delivery_id', $saleDelivery->id)
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $qty = $this->decimal($line->quantity ?? 0);
            $quant = $this->lockQuantForDeliveryLine($saleDelivery, $line);

            if (! $quant) {
                continue;
            }

            DB::table('stock_quants')
                ->where('id', $quant->id)
                ->update($this->filterTableColumns('stock_quants', [
                    'reserved_quantity' => max(0, $this->decimal($quant->reserved_quantity ?? 0) - $qty),
                    'updated_at' => now(),
                ]));
        }
    }

    protected function assertCanReserve(SaleOrder $saleOrder, array $line, float $qty): void
    {
        $query = DB::table('stock_quants')
            ->where('company_id', $saleOrder->company_id)
            ->where('warehouse_id', $saleOrder->warehouse_id)
            ->where('location_id', $saleOrder->location_id)
            ->where('product_id', $line['product_id']);

        $variantId = (int) ($line['product_variant_id'] ?? 0);

        if ($variantId > 0) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', 0);
            });
        }

        $quant = $query->lockForUpdate()->first();

        if (! $quant) {
            throw new \RuntimeException(
                'No hay existencia para '
                . ($line['product_label'] ?? 'producto')
                . ' en '
                . $this->saleOrderSourceLabel($saleOrder)
                . '. '
                . $this->availabilityHintForSaleOrderLine($saleOrder, $line, $qty)
            );
        }

        $available = $this->decimal($quant->quantity ?? 0) - $this->decimal($quant->reserved_quantity ?? 0);

        if ($available < $qty) {
            throw new \RuntimeException(
                'Existencia disponible insuficiente para '
                . ($line['product_label'] ?? 'producto')
                . ' en '
                . $this->saleOrderSourceLabel($saleOrder)
                . '. Disponible: '
                . number_format($available, 2)
                . ', requerido: '
                . number_format($qty, 2)
                . '. '
                . $this->availabilityHintForSaleOrderLine($saleOrder, $line, $qty)
            );
        }
    }

    protected function saleOrderSourceLabel(SaleOrder $saleOrder): string
    {
        return $this->warehouseLocationLabel(
            (int) ($saleOrder->warehouse_id ?? 0),
            (int) ($saleOrder->location_id ?? 0)
        );
    }

    protected function deliverySourceLabel(SaleDelivery $saleDelivery): string
    {
        return $this->warehouseLocationLabel(
            (int) ($saleDelivery->warehouse_id ?? 0),
            (int) ($saleDelivery->source_location_id ?? 0)
        );
    }

    protected function warehouseLocationLabel(int $warehouseId, int $locationId): string
    {
        $warehouse = $warehouseId > 0 && Schema::hasTable('warehouses')
            ? DB::table('warehouses')->where('id', $warehouseId)->first()
            : null;

        $location = $locationId > 0 && Schema::hasTable('stock_locations')
            ? DB::table('stock_locations')->where('id', $locationId)->first()
            : null;

        $warehouseName = $warehouse
            ? trim((string) ($warehouse->name ?? ('Almacén #' . $warehouseId)))
            : ($warehouseId > 0 ? 'Almacén #' . $warehouseId : 'almacén no definido');

        $locationName = $location
            ? trim((string) ($location->name ?? ('Ubicación #' . $locationId)))
            : ($locationId > 0 ? 'Ubicación #' . $locationId : 'ubicación no definida');

        return $warehouseName . ' / ' . $locationName;
    }

    protected function availabilityHintForSaleOrderLine(SaleOrder $saleOrder, array $line, float $required): string
    {
        return $this->stockAvailabilityHint(
            (int) $saleOrder->company_id,
            (int) ($line['product_id'] ?? 0),
            (int) ($line['product_variant_id'] ?? 0),
            $required,
            (int) ($saleOrder->warehouse_id ?? 0),
            (int) ($saleOrder->location_id ?? 0)
        );
    }

    protected function availabilityHintForDeliveryLine(SaleDelivery $saleDelivery, object $line, float $required, ?int $lotId = null): string
    {
        return $this->stockAvailabilityHint(
            (int) $saleDelivery->company_id,
            (int) ($line->product_id ?? 0),
            (int) ($line->product_variant_id ?? 0),
            $required,
            (int) ($saleDelivery->warehouse_id ?? 0),
            (int) ($saleDelivery->source_location_id ?? 0),
            $lotId,
            $this->lineRequiresLotNumber($line)
        );
    }

    protected function stockAvailabilityHint(
        int $companyId,
        int $productId,
        int $variantId,
        float $required,
        int $currentWarehouseId = 0,
        int $currentLocationId = 0,
        ?int $lotId = null,
        bool $requiresLot = false
    ): string {
        if (
            $companyId <= 0
            || $productId <= 0
            || ! Schema::hasTable('stock_quants')
            || ! Schema::hasTable('warehouses')
            || ! Schema::hasTable('stock_locations')
        ) {
            return 'No fue posible consultar existencias alternativas.';
        }

        $query = DB::table('stock_quants as q')
            ->leftJoin('warehouses as w', 'w.id', '=', 'q.warehouse_id')
            ->leftJoin('stock_locations as l', 'l.id', '=', 'q.location_id')
            ->where('q.company_id', $companyId)
            ->where('q.product_id', $productId);

        if ($variantId > 0) {
            $query->where('q.product_variant_id', $variantId);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('q.product_variant_id')
                    ->orWhere('q.product_variant_id', 0);
            });
        }

        if ($lotId) {
            $query->where('q.lot_id', $lotId);
        } elseif ($requiresLot) {
            $query->whereNotNull('q.lot_id');
        }

        $rows = $query
            ->selectRaw('
                q.warehouse_id,
                q.location_id,
                COALESCE(w.name, CONCAT(\'Almacén #\', q.warehouse_id)) as warehouse_name,
                COALESCE(l.name, CONCAT(\'Ubicación #\', q.location_id)) as location_name,
                SUM(q.quantity) as quantity,
                SUM(COALESCE(q.reserved_quantity, 0)) as reserved,
                SUM(q.quantity - COALESCE(q.reserved_quantity, 0)) as available
            ')
            ->groupBy('q.warehouse_id', 'q.location_id', 'w.name', 'l.name')
            ->havingRaw('SUM(q.quantity - COALESCE(q.reserved_quantity, 0)) > 0')
            ->orderByDesc(DB::raw('SUM(q.quantity - COALESCE(q.reserved_quantity, 0))'))
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            $activeSourceCount = DB::table('stock_locations as l')
                ->join('warehouses as w', 'w.id', '=', 'l.warehouse_id')
                ->where('l.company_id', $companyId)
                ->where('w.company_id', $companyId)
                ->where('l.is_active', true)
                ->where('w.is_active', true)
                ->count();

            $totalAvailableCompanyQuery = DB::table('stock_quants')
                ->where('company_id', $companyId)
                ->where('product_id', $productId);

            if ($variantId > 0) {
                $totalAvailableCompanyQuery->where('product_variant_id', $variantId);
            } else {
                $totalAvailableCompanyQuery->where(function ($q): void {
                    $q->whereNull('product_variant_id')
                        ->orWhere('product_variant_id', 0);
                });
            }

            $totalAvailableCompany = (float) $totalAvailableCompanyQuery
                ->selectRaw('COALESCE(SUM(quantity - COALESCE(reserved_quantity, 0)), 0) as available')
                ->value('available');

            if ($totalAvailableCompany <= 0) {
                return 'No se encontró existencia disponible de este producto en ningún almacén de esta empresa. Registra una recepción, ajuste de inventario o transferencia antes de crear la entrega.';
            }

            if ((int) $activeSourceCount <= 1) {
                return 'Esta empresa solo tiene un almacén/ubicación activa para sugerir y no hay existencia suficiente ahí. Registra una recepción, ajuste de inventario o transferencia antes de crear la entrega.';
            }

            return 'No se encontró existencia disponible de este producto en otros almacenes/ubicaciones de esta empresa. Registra una recepción, ajuste de inventario o transferencia antes de crear la entrega.';
        }

        $sufficient = $rows->filter(function ($row) use ($required): bool {
            return $this->decimal($row->available ?? 0) + 0.000001 >= $required;
        });

        $displayRows = ($sufficient->isNotEmpty() ? $sufficient : $rows)->take(3);

        $locations = $displayRows
            ->map(function ($row): string {
                $label = trim((string) ($row->warehouse_name ?? ('Almacén #' . $row->warehouse_id)))
                    . ' / '
                    . trim((string) ($row->location_name ?? ('Ubicación #' . $row->location_id)));

                return $label . ' (disponible: ' . number_format($this->decimal($row->available ?? 0), 2) . ')';
            })
            ->implode('; ');

        $prefix = $sufficient->isNotEmpty()
            ? 'Sí hay existencia suficiente en: '
            : 'Hay existencia parcial en: ';

        $action = $sufficient->isNotEmpty()
            ? ' Cambia el almacén/ubicación de la orden o mueve inventario antes de crear la entrega.'
            : ' Mueve inventario o ajusta la cantidad a entregar.';

        if ($currentWarehouseId > 0 && $currentLocationId > 0) {
            $action .= ' Almacén actual: ' . $this->warehouseLocationLabel($currentWarehouseId, $currentLocationId) . '.';
        }

        return $prefix . $locations . '.' . $action;
    }

    protected function detectDeliveryType($pendingLines, array $rows): string
    {
        $quantitiesByLineId = collect($rows)
            ->mapWithKeys(fn (array $row): array => [(int) $row['line']['id'] => $this->decimal($row['quantity'])]);

        foreach ($pendingLines as $line) {
            $pending = $this->decimal($line['_pending']);
            $qty = $this->decimal($quantitiesByLineId[(int) $line['id']] ?? 0);

            if ($pending > 0 && abs($qty - $pending) > 0.000001) {
                return 'partial';
            }
        }

        return 'complete';
    }

    protected function pendingLines(SaleOrder $saleOrder): array
    {
        $totals = $this->reservedDeliveryTotals($saleOrder->id);

        return DB::table('sales_order_lines')
            ->where('sales_order_id', $saleOrder->id)
            ->orderBy('id')
            ->get()
            ->map(function ($line) use ($totals): array {
                $ordered = $this->decimal($line->quantity ?? 0);
                $delivered = $this->decimal($line->delivered_quantity ?? 0);
                $reserved = $this->decimal($totals[$line->id] ?? 0);
                $covered = max($delivered, $reserved);

                return [
                    'id' => (int) $line->id,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'product_label' => $line->product_label ?: 'Producto',
                    'variant_label' => $line->variant_label,
                    'estimated_unit_cost_without_tax' => $line->estimated_unit_cost_without_tax ?? 0,
                    '_ordered' => $ordered,
                    '_delivered' => $delivered,
                    '_reserved' => $reserved,
                    '_covered' => $covered,
                    '_pending' => max(0, $ordered - $covered),
                ];
            })
            ->all();
    }

    protected function reservedDeliveryTotals(int $saleOrderId): array
    {
        if (! Schema::hasTable('sale_deliveries') || ! Schema::hasTable('sale_delivery_lines')) {
            return [];
        }

        return DB::table('sale_delivery_lines as l')
            ->join('sale_deliveries as d', 'd.id', '=', 'l.sale_delivery_id')
            ->where('d.sales_order_id', $saleOrderId)
            ->where('d.status', '!=', 'cancelled')
            ->groupBy('l.sales_order_line_id')
            ->selectRaw('l.sales_order_line_id, SUM(l.quantity) as total_quantity')
            ->pluck('total_quantity', 'sales_order_line_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    protected function doneDeliveryTotals(int $saleOrderId): array
    {
        if (! Schema::hasTable('sale_deliveries') || ! Schema::hasTable('sale_delivery_lines')) {
            return [];
        }

        return DB::table('sale_delivery_lines as l')
            ->join('sale_deliveries as d', 'd.id', '=', 'l.sale_delivery_id')
            ->where('d.sales_order_id', $saleOrderId)
            ->where('d.status', 'done')
            ->groupBy('l.sales_order_line_id')
            ->selectRaw('l.sales_order_line_id, SUM(l.quantity) as total_quantity')
            ->pluck('total_quantity', 'sales_order_line_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    protected function refreshSalesOrderDeliveryStatus(int $saleOrderId): void
    {
        $order = DB::table('sales_orders')->where('id', $saleOrderId)->first();

        if (! $order) {
            return;
        }

        $totals = $this->doneDeliveryTotals($saleOrderId);

        $lines = DB::table('sales_order_lines')
            ->where('sales_order_id', $saleOrderId)
            ->orderBy('id')
            ->get();

        $anyDelivered = false;
        $allDelivered = $lines->isNotEmpty();
        $deliveredTotal = 0.0;

        foreach ($lines as $line) {
            $ordered = $this->decimal($line->quantity ?? 0);
            $delivered = min($ordered, $this->decimal($totals[$line->id] ?? 0));
            $deliveredTotal += $delivered;

            if ($delivered > 0) {
                $anyDelivered = true;
            }

            if ($ordered > 0 && $delivered + 0.000001 < $ordered) {
                $allDelivered = false;
            }

            $lineStatus = 'pending';

            if ($ordered > 0 && $delivered + 0.000001 >= $ordered) {
                $lineStatus = 'delivered';
            } elseif ($delivered > 0) {
                $lineStatus = 'partial';
            }

            DB::table('sales_order_lines')
                ->where('id', $line->id)
                ->update($this->filterTableColumns('sales_order_lines', [
                    'delivered_quantity' => $delivered,
                    'delivery_status' => $lineStatus,
                    'updated_at' => now(),
                ]));
        }

        $orderStatus = (string) ($order->status ?? '');

        if (in_array($orderStatus, ['confirmed', 'partially_delivered', 'delivered'], true)) {
            $newStatus = $allDelivered && $anyDelivered
                ? 'delivered'
                : ($anyDelivered ? 'partially_delivered' : 'confirmed');

            DB::table('sales_orders')
                ->where('id', $saleOrderId)
                ->update($this->filterTableColumns('sales_orders', [
                    'status' => $newStatus,
                    'delivered_total_quantity' => $deliveredTotal,
                    'updated_at' => now(),
                ]));
        }
    }

    protected function lockQuantForDeliveryLine(SaleDelivery $saleDelivery, object $line, ?int $preferredLotId = null): ?object
    {
        $query = DB::table('stock_quants')
            ->where('company_id', $saleDelivery->company_id)
            ->where('warehouse_id', $saleDelivery->warehouse_id)
            ->where('location_id', $saleDelivery->source_location_id)
            ->where('product_id', $line->product_id);

        $variantId = (int) ($line->product_variant_id ?? 0);

        if ($variantId > 0) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', 0);
            });
        }

        $lineLotId = $preferredLotId ?: (! empty($line->stock_lot_id) ? (int) $line->stock_lot_id : null);

        if ($lineLotId) {
            $query->where('lot_id', $lineLotId);
        } elseif ($this->lineRequiresLotNumber($line)) {
            $query->whereNotNull('lot_id');
        }

        return $query
            ->where('quantity', '>', 0)
            ->orderByRaw('CASE WHEN lot_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    protected function normalizeLotSelections(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $lineId => $lotId) {
            if (is_numeric($lineId) && is_numeric($lotId) && (int) $lineId > 0 && (int) $lotId > 0) {
                $out[(int) $lineId] = (int) $lotId;
            }
        }

        return $out;
    }

    protected function selectedLotIdForDeliveryLine(object $line, mixed $requestedLotId = null): ?int
    {
        $lotId = is_numeric($requestedLotId) && (int) $requestedLotId > 0
            ? (int) $requestedLotId
            : (! empty($line->stock_lot_id) ? (int) $line->stock_lot_id : null);

        if (! $lotId || ! Schema::hasTable('stock_lots')) {
            return null;
        }

        $query = DB::table('stock_lots')
            ->where('id', $lotId)
            ->where('company_id', $line->company_id)
            ->where('product_id', $line->product_id);

        if (! empty($line->product_variant_id)) {
            $query->where('product_variant_id', $line->product_variant_id);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', 0);
            });
        }

        return $query->exists() ? $lotId : null;
    }

    protected function lineRequiresLotNumber(object $line): bool
    {
        if (! Schema::hasTable('products')) {
            return false;
        }

        $productIds = array_values(array_unique(array_filter([
            ! empty($line->product_variant_id) ? (int) $line->product_variant_id : null,
            ! empty($line->product_id) ? (int) $line->product_id : null,
        ])));

        foreach ($productIds as $productId) {
            $product = DB::table('products')->where('id', $productId)->first();

            if (! $product) {
                continue;
            }

            $tracking = strtolower(trim((string) ($product->tracking ?? '')));
            $advancedMode = strtolower(trim((string) ($product->advanced_tracking_mode ?? '')));

            if (
                str_contains($tracking, 'lot')
                || str_contains($tracking, 'lote')
                || str_contains($advancedMode, 'lot')
                || str_contains($advancedMode, 'lote')
            ) {
                return true;
            }
        }

        return false;
    }

    protected function lotTrackingContextForDeliveryLine(SaleDelivery $saleDelivery, object $line, int $lotId, ?int $movementLineId = null): array
    {
        $lot = Schema::hasTable('stock_lots')
            ? DB::table('stock_lots')->where('id', $lotId)->first()
            : null;

        return [
            'stock_lot_id' => $lotId,
            'lot_number' => $lot->lot_number ?? null,
            'source_type' => 'sale_delivery',
            'source_id' => (int) $saleDelivery->id,
            'source_line_type' => 'sale_delivery_line',
            'source_line_id' => (int) ($line->id ?? 0),
            'stock_movement_line_id' => $movementLineId,
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    protected function releaseMismatchedLotReservation(SaleDelivery $saleDelivery, object $line, int $newLotId, float $qty, mixed $now): void
    {
        $oldLotId = ! empty($line->stock_lot_id) ? (int) $line->stock_lot_id : null;

        if ($oldLotId === $newLotId) {
            return;
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $saleDelivery->company_id)
            ->where('warehouse_id', $saleDelivery->warehouse_id)
            ->where('location_id', $saleDelivery->source_location_id)
            ->where('product_id', $line->product_id);

        $variantId = (int) ($line->product_variant_id ?? 0);

        if ($variantId > 0) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', 0);
            });
        }

        $oldLotId
            ? $query->where('lot_id', $oldLotId)
            : $query->whereNull('lot_id');

        $oldQuant = $query->lockForUpdate()->first();

        if (! $oldQuant) {
            return;
        }

        DB::table('stock_quants')
            ->where('id', $oldQuant->id)
            ->update($this->filterTableColumns('stock_quants', [
                'reserved_quantity' => max(0, $this->decimal($oldQuant->reserved_quantity ?? 0) - $qty),
                'updated_at' => $now,
            ]));
    }

    protected function stockOperationTypeId(int $companyId): ?int
    {
        if (! Schema::hasTable('stock_operation_types')) {
            return null;
        }

        $columns = Schema::getColumnListing('stock_operation_types');
        $query = DB::table('stock_operation_types');

        if (in_array('company_id', $columns, true)) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        $id = $query->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    protected function nextNumber(int $companyId): string
    {
        $prefix = 'ENT-VTA-' . now()->format('Ymd') . '-';

        $last = DB::table('sale_deliveries')
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if (is_string($last) && str_starts_with($last, $prefix)) {
            $next = ((int) substr($last, strlen($prefix))) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function customerLocationId(int $companyId): ?int
    {
        if (! Schema::hasTable('stock_locations')) {
            return null;
        }

        return DB::table('stock_locations')
            ->where('code', 'CLIENTES')
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->value('id');
    }

    protected function filterTableColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $data,
            fn ($value, $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected function decimal(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 6);
    }

    protected function variantSuffix(object $line): string
    {
        return ! empty($line->variant_label)
            ? ' / ' . $line->variant_label
            : '';
    }

    protected function userCanUpdateSales(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return method_exists($user, 'can') && (
            $user->can('sales.update')
            || $user->can('inventory.update')
            || $user->can('inventory.view')
        );
    }


    protected function saleOrderEditUrl(SaleDelivery $saleDelivery): string
    {
        return url('/admin/' . (int) $saleDelivery->company_id . '/sale-orders/' . (int) $saleDelivery->sales_order_id . '/edit');
    }

    protected function normalizeSerialSelections(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $lineId => $serialNumberId) {
            $lineId = (int) $lineId;
            $serialNumberId = (int) $serialNumberId;

            if ($lineId > 0 && $serialNumberId > 0) {
                $normalized[$lineId] = $serialNumberId;
            }
        }

        return $normalized;
    }

    protected function serialContextForDeliveryLine(SaleDelivery $saleDelivery, object $line, ?int $movementLineId = null): array
    {
        return [
            'company_id' => (int) ($line->company_id ?? $saleDelivery->company_id),
            'product_id' => (int) ($line->product_id ?? 0),
            'product_variant_id' => ! empty($line->product_variant_id) ? (int) $line->product_variant_id : null,
            'stock_movement_line_id' => $movementLineId,
            'source_type' => 'sale_delivery',
            'source_id' => (int) $saleDelivery->id,
            'source_line_type' => 'sale_delivery_line',
            'source_line_id' => (int) ($line->id ?? 0),
            'user_id' => auth()->id(),
        ];
    }

    protected function lineRequiresSerialNumber(object $line): bool
    {
        if ($this->lineHasAvailableSerialNumbers($line)) {
            return true;
        }

        if (! Schema::hasTable('products')) {
            return false;
        }

        $ids = [];

        if (! empty($line->product_variant_id)) {
            $ids[] = (int) $line->product_variant_id;
        }

        if (! empty($line->product_id)) {
            $ids[] = (int) $line->product_id;
        }

        foreach (array_values(array_unique(array_filter($ids))) as $productId) {
            $product = DB::table('products')->where('id', $productId)->first();

            if (! $product) {
                continue;
            }

            foreach (['tracking', 'advanced_tracking_mode'] as $column) {
                $value = strtolower(trim((string) ($product->{$column} ?? '')));

                if ($value !== '' && (str_contains($value, 'serial') || str_contains($value, 'serie'))) {
                    return true;
                }
            }

            $fields = $product->advanced_tracking_fields ?? null;

            if (is_string($fields) && $fields !== '') {
                $decoded = json_decode($fields, true);

                if (is_array($decoded)) {
                    $flat = strtolower(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

                    if (str_contains($flat, 'serial') || str_contains($flat, 'serie')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function lineHasAvailableSerialNumbers(object $line): bool
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return false;
        }

        $companyId = (int) ($line->company_id ?? 0);
        $productId = (int) ($line->product_id ?? 0);

        if ($companyId <= 0 || $productId <= 0) {
            return false;
        }

        $query = DB::table('stock_serial_numbers')
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('status', 'available');

        if (! empty($line->product_variant_id)) {
            $query->where('product_variant_id', (int) $line->product_variant_id);
        } else {
            $query->where(function ($inner): void {
                $inner->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', 0);
            });
        }

        return $query->exists();
    }

}
