@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $order = $saleOrderId ? DB::table('sales_orders')->where('id', $saleOrderId)->first() : null;
    $tablesReady = Schema::hasTable('sale_deliveries') && Schema::hasTable('sale_delivery_lines');

    $lines = collect();
    $deliveries = collect();
    $deliveryLines = collect();
    $stockWarnings = collect();

    if ($order && $tablesReady) {
        $reservedTotals = DB::table('sale_delivery_lines as l')
            ->join('sale_deliveries as d', 'd.id', '=', 'l.sale_delivery_id')
            ->where('d.sales_order_id', $order->id)
            ->where('d.status', '!=', 'cancelled')
            ->groupBy('l.sales_order_line_id')
            ->selectRaw('l.sales_order_line_id, SUM(l.quantity) as total_quantity')
            ->pluck('total_quantity', 'sales_order_line_id');

        $lines = DB::table('sales_order_lines')
            ->where('sales_order_id', $order->id)
            ->orderBy('id')
            ->get()
            ->map(function ($line) use ($reservedTotals) {
                $requested = (float) ($line->quantity ?? 0);
                $delivered = (float) ($line->delivered_quantity ?? 0);
                $reserved = (float) ($reservedTotals[$line->id] ?? 0);
                $covered = max($delivered, $reserved);

                $line->_requested = $requested;
                $line->_reserved = $reserved;
                $line->_covered = $covered;
                $line->_pending = max(0, $requested - $covered);

                return $line;
            });

        $deliveries = DB::table('sale_deliveries')
            ->where('sales_order_id', $order->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        if (
            $order
            && Schema::hasTable('stock_quants')
            && Schema::hasTable('warehouses')
            && Schema::hasTable('stock_locations')
            && ! empty($order->warehouse_id)
            && ! empty($order->location_id)
        ) {
            $currentWarehouse = DB::table('warehouses')->where('id', $order->warehouse_id)->first();
            $currentLocation = DB::table('stock_locations')->where('id', $order->location_id)->first();

            $currentSourceLabel = trim((string) ($currentWarehouse->name ?? ('Almacén #' . $order->warehouse_id)))
                . ' / '
                . trim((string) ($currentLocation->name ?? ('Ubicación #' . $order->location_id)));

            foreach ($lines as $line) {
                if ((float) ($line->_pending ?? 0) <= 0) {
                    continue;
                }

                $variantId = (int) ($line->product_variant_id ?? 0);

                $currentQuery = DB::table('stock_quants')
                    ->where('company_id', $order->company_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->where('location_id', $order->location_id)
                    ->where('product_id', $line->product_id);

                if ($variantId > 0) {
                    $currentQuery->where('product_variant_id', $variantId);
                } else {
                    $currentQuery->where(function ($query) {
                        $query->whereNull('product_variant_id')
                            ->orWhere('product_variant_id', 0);
                    });
                }

                $currentAvailable = (float) $currentQuery
                    ->selectRaw('COALESCE(SUM(quantity - COALESCE(reserved_quantity, 0)), 0) as available')
                    ->value('available');

                if ($currentAvailable + 0.000001 >= (float) $line->_pending) {
                    continue;
                }

                $altQuery = DB::table('stock_quants as q')
                    ->leftJoin('warehouses as w', 'w.id', '=', 'q.warehouse_id')
                    ->leftJoin('stock_locations as l', 'l.id', '=', 'q.location_id')
                    ->where('q.company_id', $order->company_id)
                    ->where('q.product_id', $line->product_id)
                    ->where(function ($query) use ($order) {
                        $query->where('q.warehouse_id', '!=', $order->warehouse_id)
                            ->orWhere('q.location_id', '!=', $order->location_id);
                    });

                if ($variantId > 0) {
                    $altQuery->where('q.product_variant_id', $variantId);
                } else {
                    $altQuery->where(function ($query) {
                        $query->whereNull('q.product_variant_id')
                            ->orWhere('q.product_variant_id', 0);
                    });
                }

                $alternatives = $altQuery
                    ->selectRaw("
                        q.warehouse_id,
                        q.location_id,
                        COALESCE(w.name, CONCAT('Almacén #', q.warehouse_id)) as warehouse_name,
                        COALESCE(l.name, CONCAT('Ubicación #', q.location_id)) as location_name,
                        SUM(q.quantity - COALESCE(q.reserved_quantity, 0)) as available
                    ")
                    ->groupBy('q.warehouse_id', 'q.location_id', 'w.name', 'l.name')
                    ->havingRaw('SUM(q.quantity - COALESCE(q.reserved_quantity, 0)) > 0')
                    ->orderByDesc(DB::raw('SUM(q.quantity - COALESCE(q.reserved_quantity, 0))'))
                    ->limit(3)
                    ->get();

                $sufficient = $alternatives->filter(function ($alt) use ($line) {
                    return (float) ($alt->available ?? 0) + 0.000001 >= (float) ($line->_pending ?? 0);
                });

                $displayAlternatives = ($sufficient->isNotEmpty() ? $sufficient : $alternatives)
                    ->map(function ($alt) {
                        return trim((string) $alt->warehouse_name)
                            . ' / '
                            . trim((string) $alt->location_name)
                            . ' (disponible: '
                            . number_format((float) ($alt->available ?? 0), 2)
                            . ')';
                    })
                    ->values()
                    ->all();

                $activeSourceCount = DB::table('stock_locations as l')
                    ->join('warehouses as w', 'w.id', '=', 'l.warehouse_id')
                    ->where('l.company_id', $order->company_id)
                    ->where('w.company_id', $order->company_id)
                    ->where('l.is_active', true)
                    ->where('w.is_active', true)
                    ->count();

                $totalAvailableCompany = DB::table('stock_quants')
                    ->where('company_id', $order->company_id)
                    ->where('product_id', $line->product_id)
                    ->when($variantId > 0, fn ($query) => $query->where('product_variant_id', $variantId))
                    ->when($variantId <= 0, function ($query) {
                        $query->where(function ($sub) {
                            $sub->whereNull('product_variant_id')
                                ->orWhere('product_variant_id', 0);
                        });
                    })
                    ->selectRaw('COALESCE(SUM(quantity - COALESCE(reserved_quantity, 0)), 0) as available')
                    ->value('available');

                $stockWarnings->push([
                    'product' => $line->product_label ?: 'Producto',
                    'pending' => (float) ($line->_pending ?? 0),
                    'current_available' => $currentAvailable,
                    'current_source' => $currentSourceLabel,
                    'alternatives' => $displayAlternatives,
                    'has_sufficient_alternative' => $sufficient->isNotEmpty(),
                    'active_source_count' => (int) $activeSourceCount,
                    'total_available_company' => (float) $totalAvailableCompany,
                ]);
            }
        }
    }

    $canCreateDelivery = $order && in_array((string) ($order->status ?? ''), ['confirmed', 'partially_delivered'], true);
@endphp

<div class="space-y-4" id="bexia-delivery-screen">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @if(! $order)
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
            Guarda la orden para poder crear entregas.
        </div>
    @elseif(! $tablesReady)
        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
            Faltan las tablas de entregas. Ejecuta las migraciones de V5.29.3.
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-3">
                <div class="text-sm font-semibold text-gray-900">Crear entrega y reservar inventario</div>
                <div class="text-xs text-gray-500">
                    Captura la cantidad a entregar. El sistema calculará si la entrega es completa o parcial.
                    Al crear la entrega se reserva inventario. Al validar, se genera la salida y se descuenta existencia.
                </div>
            </div>

            <div
                id="bexia-partial-warning"
                class="mb-3 hidden rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
            >
                Se creará una entrega parcial. Quedará una entrega pendiente relacionada a esta venta.
            </div>

            <form
                method="POST"
                action="{{ route('sales-orders.deliveries.partial', ['saleOrder' => $order->id]) }}"
                id="bexia-delivery-form"
            >
                @csrf

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Producto</th>
                                <th class="px-3 py-2 text-right">Cantidad solicitada</th>
                                <th class="px-3 py-2 text-right">Cantidad reservada</th>
                                <th class="px-3 py-2 text-right">Pendiente</th>
                                <th class="px-3 py-2 text-right">Cantidad a entregar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $line->product_label ?: 'Producto' }}</div>
                                        @if($line->variant_label)
                                            <div class="text-xs text-gray-500">{{ $line->variant_label }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ number_format($line->_requested, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($line->_reserved, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ number_format($line->_pending, 2) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <input
                                            type="number"
                                            step="0.000001"
                                            min="0"
                                            max="{{ $line->_pending }}"
                                            name="line_quantities[{{ $line->id }}]"
                                            data-pending="{{ $line->_pending }}"
                                            class="bexia-delivery-qty w-32 rounded-lg border-gray-300 text-right text-sm"
                                            @disabled(! $canCreateDelivery || $line->_pending <= 0)
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <textarea
                        name="notes"
                        rows="2"
                        class="w-full rounded-lg border-gray-300 text-sm"
                        placeholder="Notas de entrega, opcional"
                        @disabled(! $canCreateDelivery)
                    ></textarea>
                </div>

                <div class="mt-4 flex justify-end" id="bexia-create-delivery-button-wrapper">
                    <button
                        type="submit"
                        id="bexia-create-delivery-button"
                        data-bexia-create-delivery-button="1"
                        @disabled(! $canCreateDelivery)
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm disabled:opacity-50"
                        style="display: inline-flex !important; align-items: center; justify-content: center; visibility: visible !important;"
                    >
                        Crear entrega
                    </button>
                </div>
</form>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-semibold text-gray-900">Entregas creadas</div>

            @forelse($deliveries as $delivery)
                <div class="mb-3 rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-medium text-gray-900">{{ $delivery->number ?: ('Entrega #' . $delivery->id) }}</div>
                            @php
                                $deliveryStatusLabel = match ((string) $delivery->status) {
                                    'draft' => 'Borrador',
                                    'done' => 'Validada',
                                    'cancelled' => 'Cancelada',
                                    default => $delivery->status ?: 'Sin estado',
                                };

                                $deliveryTypeLabel = match ((string) $delivery->delivery_type) {
                                    'complete' => 'Completa',
                                    'partial' => 'Parcial',
                                    default => $delivery->delivery_type ?: 'Sin tipo',
                                };
                            @endphp

                            <div class="text-xs text-gray-500">
                                Estado: {{ $deliveryStatusLabel }} · Tipo: {{ $deliveryTypeLabel }} · {{ $delivery->created_at }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('sales.deliveries.print', ['saleDelivery' => $delivery->id]) }}"
                                target="_blank"
                                class="rounded-lg border border-blue-200 px-3 py-1 text-xs font-semibold text-blue-700"
                            >
                                Imprimir
                            </a>

                            @if($delivery->status === 'draft')
                                <form
                                    method="POST"
                                    action="{{ route('sales-deliveries.validate', ['saleDelivery' => $delivery->id]) }}"
                                    id="bexia-validate-delivery-form-{{ $delivery->id }}"
                                    data-bexia-validate-delivery-form="1"
                                >
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-green-200 px-3 py-1 text-xs font-semibold text-green-700">
                                        Validar entrega
                                    </button>
</form>

                                <form method="POST" action="{{ route('sales-deliveries.cancel', ['saleDelivery' => $delivery->id]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-700">
                                        Cancelar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="mt-2 text-xs text-gray-600">
                        @foreach(($deliveryLines[$delivery->id] ?? collect()) as $dLine)
                            @php
                                $selectedSerialId = ! empty($dLine->stock_serial_number_id) ? (int) $dLine->stock_serial_number_id : null;
                                $requiresSerial = false;
                                $serialOptions = collect();
                                $quantityForSerial = (float) ($dLine->quantity ?? 0);

                                $productIdsToCheck = array_values(array_unique(array_filter([
                                    ! empty($dLine->product_variant_id) ? (int) $dLine->product_variant_id : null,
                                    ! empty($dLine->product_id) ? (int) $dLine->product_id : null,
                                ])));

                                if (Schema::hasTable('products')) {
                                    foreach ($productIdsToCheck as $productIdToCheck) {
                                        $trackingProduct = DB::table('products')->where('id', $productIdToCheck)->first();

                                        if (! $trackingProduct) {
                                            continue;
                                        }

                                        $trackingValue = strtolower(trim((string) ($trackingProduct->tracking ?? '')));
                                        $advancedTrackingMode = strtolower(trim((string) ($trackingProduct->advanced_tracking_mode ?? '')));

                                        if (
                                            str_contains($trackingValue, 'serial')
                                            || str_contains($trackingValue, 'serie')
                                            || str_contains($advancedTrackingMode, 'serial')
                                            || str_contains($advancedTrackingMode, 'serie')
                                        ) {
                                            $requiresSerial = true;
                                            break;
                                        }

                                        $advancedFields = $trackingProduct->advanced_tracking_fields ?? null;

                                        if (is_string($advancedFields) && $advancedFields !== '') {
                                            $decodedFields = json_decode($advancedFields, true);

                                            if (is_array($decodedFields)) {
                                                $flatFields = strtolower(json_encode($decodedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

                                                if (str_contains($flatFields, 'serial') || str_contains($flatFields, 'serie')) {
                                                    $requiresSerial = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }

                                if (! $requiresSerial && Schema::hasTable('stock_serial_numbers')) {
                                    $hasSerialQuery = DB::table('stock_serial_numbers')
                                        ->where('company_id', $dLine->company_id)
                                        ->where('product_id', $dLine->product_id)
                                        ->where('status', 'available');

                                    if (! empty($dLine->product_variant_id)) {
                                        $hasSerialQuery->where('product_variant_id', $dLine->product_variant_id);
                                    } else {
                                        $hasSerialQuery->where(function ($query) {
                                            $query->whereNull('product_variant_id')
                                                ->orWhere('product_variant_id', 0);
                                        });
                                    }

                                    $requiresSerial = $hasSerialQuery->exists();
                                }

                                if (
                                    $delivery->status === 'draft'
                                    && ($requiresSerial || $selectedSerialId)
                                    && Schema::hasTable('stock_serial_numbers')
                                ) {
                                    $serialCompanyId = (int) ($dLine->company_id ?? 0);
                                    $serialProductId = (int) ($dLine->product_id ?? 0);
                                    $serialVariantId = ! empty($dLine->product_variant_id) ? (int) $dLine->product_variant_id : null;

                                    $serialQuery = DB::table('stock_serial_numbers')
                                        ->where('company_id', $serialCompanyId)
                                        ->where('product_id', $serialProductId)
                                        ->where(function ($query) use ($selectedSerialId) {
                                            $query->where('status', 'available');

                                            if ($selectedSerialId) {
                                                $query->orWhere('id', $selectedSerialId);
                                            }
                                        });

                                    if ($serialVariantId) {
                                        $serialQuery->where('product_variant_id', $serialVariantId);
                                    } else {
                                        $serialQuery->where(function ($query) {
                                            $query->whereNull('product_variant_id')
                                                ->orWhere('product_variant_id', 0);
                                        });
                                    }

                                    $serialOptions = $serialQuery
                                        ->whereNotNull('serial_number')
                                        ->orderBy('serial_number')
                                        ->limit(100)
                                        ->get()
                                        ->filter(function ($serialOption) use ($serialCompanyId, $serialProductId, $serialVariantId) {
                                            if ((int) ($serialOption->company_id ?? 0) !== $serialCompanyId) {
                                                return false;
                                            }

                                            if ((int) ($serialOption->product_id ?? 0) !== $serialProductId) {
                                                return false;
                                            }

                                            $optionVariantId = ! empty($serialOption->product_variant_id) ? (int) $serialOption->product_variant_id : null;

                                            return $serialVariantId
                                                ? $optionVariantId === $serialVariantId
                                                : $optionVariantId === null;
                                        })
                                        ->values();
                                }

                                $selectedSerialLabel = $selectedSerialId && Schema::hasTable('stock_serial_numbers')
                                    ? DB::table('stock_serial_numbers')->where('id', $selectedSerialId)->value('serial_number')
                                    : null;

                                $selectedLotId = ! empty($dLine->stock_lot_id) ? (int) $dLine->stock_lot_id : null;
                                $requiresLot = false;
                                $lotOptions = collect();

                                if (Schema::hasTable('products')) {
                                    foreach ($productIdsToCheck as $productIdToCheck) {
                                        $trackingProduct = DB::table('products')->where('id', $productIdToCheck)->first();

                                        if (! $trackingProduct) {
                                            continue;
                                        }

                                        $trackingValue = strtolower(trim((string) ($trackingProduct->tracking ?? '')));
                                        $advancedTrackingMode = strtolower(trim((string) ($trackingProduct->advanced_tracking_mode ?? '')));

                                        if (
                                            str_contains($trackingValue, 'lot')
                                            || str_contains($trackingValue, 'lote')
                                            || str_contains($advancedTrackingMode, 'lot')
                                            || str_contains($advancedTrackingMode, 'lote')
                                        ) {
                                            $requiresLot = true;
                                            break;
                                        }

                                        $advancedFields = $trackingProduct->advanced_tracking_fields ?? null;

                                        if (is_string($advancedFields) && $advancedFields !== '') {
                                            $flatFields = strtolower($advancedFields);

                                            if (str_contains($flatFields, 'lot') || str_contains($flatFields, 'lote')) {
                                                $requiresLot = true;
                                                break;
                                            }
                                        }
                                    }
                                }

                                if (Schema::hasTable('stock_lots')) {
                                    $lotCheckQuery = DB::table('stock_lots')
                                        ->where('company_id', $dLine->company_id)
                                        ->where('product_id', $dLine->product_id);

                                    if (! empty($dLine->product_variant_id)) {
                                        $lotCheckQuery->where('product_variant_id', $dLine->product_variant_id);
                                    } else {
                                        $lotCheckQuery->where(function ($query) {
                                            $query->whereNull('product_variant_id')
                                                ->orWhere('product_variant_id', 0);
                                        });
                                    }

                                    $requiresLot = $requiresLot || $lotCheckQuery->exists();
                                }

                                if (
                                    $delivery->status === 'draft'
                                    && ($requiresLot || $selectedLotId)
                                    && Schema::hasTable('stock_lots')
                                    && Schema::hasTable('stock_quants')
                                ) {
                                    $lotCompanyId = (int) ($dLine->company_id ?? 0);
                                    $lotProductId = (int) ($dLine->product_id ?? 0);
                                    $lotVariantId = ! empty($dLine->product_variant_id) ? (int) $dLine->product_variant_id : null;

                                    $lotOptions = DB::table('stock_lots as l')
                                        ->join('stock_quants as q', 'q.lot_id', '=', 'l.id')
                                        ->where('l.company_id', $lotCompanyId)
                                        ->where('l.product_id', $lotProductId)
                                        ->where('q.company_id', $lotCompanyId)
                                        ->where('q.product_id', $lotProductId)
                                        ->where('q.warehouse_id', $delivery->warehouse_id)
                                        ->where('q.location_id', $delivery->source_location_id)
                                        ->where('q.quantity', '>', 0)
                                        ->where(function ($query) use ($selectedLotId) {
                                            $query->whereRaw('(q.quantity - q.reserved_quantity) > 0');

                                            if ($selectedLotId) {
                                                $query->orWhere('l.id', $selectedLotId);
                                            }
                                        });

                                    if ($lotVariantId) {
                                        $lotOptions->where('l.product_variant_id', $lotVariantId)
                                            ->where('q.product_variant_id', $lotVariantId);
                                    } else {
                                        $lotOptions->where(function ($query) {
                                            $query->whereNull('l.product_variant_id')
                                                ->orWhere('l.product_variant_id', 0);
                                        })->whereNull('q.product_variant_id');
                                    }

                                    $lotOptions = $lotOptions
                                        ->select([
                                            'l.id',
                                            'l.lot_number',
                                            'l.expiration_date',
                                            'q.quantity',
                                            'q.reserved_quantity',
                                        ])
                                        ->orderBy('l.expiration_date')
                                        ->orderBy('l.id')
                                        ->limit(100)
                                        ->get();
                                }

                                $selectedLotLabel = $selectedLotId && Schema::hasTable('stock_lots')
                                    ? DB::table('stock_lots')->where('id', $selectedLotId)->value('lot_number')
                                    : null;
                            @endphp

                            <div class="mb-2 rounded-lg border border-gray-100 bg-gray-50 p-2">
                                <div>
                                    {{ $dLine->product_label }}@if($dLine->variant_label) - {{ $dLine->variant_label }}@endif:
                                    <strong>{{ number_format((float) $dLine->quantity, 2) }}</strong>
                                </div>

                                @if($delivery->status === 'draft' && $requiresLot)
                                    <div class="mt-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-700">
                                            Lote requerido
                                        </label>

                                        <select
                                            name="lot_numbers[{{ $dLine->id }}]"
                                            form="bexia-validate-delivery-form-{{ $delivery->id }}"
                                            class="w-full rounded-lg border-gray-300 text-xs"
                                            required
                                        >
                                            <option value="">Selecciona lote</option>
                                            @foreach($lotOptions as $lotOption)
                                                @php
                                                    $availableLotQty = max(0, (float) ($lotOption->quantity ?? 0) - (float) ($lotOption->reserved_quantity ?? 0));
                                                @endphp
                                                <option value="{{ $lotOption->id }}" @selected($selectedLotId === (int) $lotOption->id)>
                                                    {{ $lotOption->lot_number }}
                                                    @if($lotOption->expiration_date)
                                                        · vence {{ $lotOption->expiration_date }}
                                                    @endif
                                                    · disp. {{ number_format($availableLotQty, 2) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($selectedLotLabel)
                                    <div class="mt-1 text-xs text-gray-600">
                                        Lote: <span class="font-semibold">{{ $selectedLotLabel }}</span>
                                    </div>
                                @endif

                                @if($delivery->status === 'draft' && $requiresSerial)
                                    <div class="mt-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-700">
                                            Numero de serie requerido
                                        </label>

                                        <select
                                            name="serial_numbers[{{ $dLine->id }}]"
                                            form="bexia-validate-delivery-form-{{ $delivery->id }}"
                                            class="w-full rounded-lg border-gray-300 text-xs"
                                            required
                                            data-product-id="{{ $dLine->product_id }}"
                                            data-product-variant-id="{{ $dLine->product_variant_id }}"
                                        >
                                            <option value="">Selecciona numero de serie de este producto...</option>
                                            @foreach($serialOptions as $serialOption)
                                                <option value="{{ $serialOption->id }}" @selected($selectedSerialId === (int) $serialOption->id)>
                                                    {{ $serialOption->serial_number }}
                                                    @if(! empty($serialOption->motor_number))
                                                        / Motor: {{ $serialOption->motor_number }}
                                                    @endif
                                                    @if(! empty($serialOption->customs_entry_number))
                                                        / Pedimento: {{ $serialOption->customs_entry_number }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>

                                        <div class="mt-1 text-[11px] text-gray-500">
                                            Filtro aplicado: producto #{{ $dLine->product_id }}
                                            @if(! empty($dLine->product_variant_id))
                                                / variante #{{ $dLine->product_variant_id }}
                                            @endif
                                            · opciones: {{ $serialOptions->count() }}
                                        </div>

                                        @if($serialOptions->isEmpty())
                                            <div class="mt-1 text-xs text-red-600">
                                                No hay numeros de serie disponibles para este producto/variante.
                                            </div>
                                        @endif

                                        @if(abs($quantityForSerial - 1.0) > 0.000001)
                                            <div class="mt-1 text-xs text-amber-700">
                                                Para productos con numero de serie, esta entrega debe tener cantidad 1.
                                            </div>
                                        @endif
                                    </div>
                                @elseif($selectedSerialLabel)
                                    <div class="mt-1 text-xs text-gray-600">
                                        Serie: <strong>{{ $selectedSerialLabel }}</strong>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-500">Todavía no hay entregas creadas.</div>
            @endforelse
        </div>
    @endif
</div>


<div
    id="bexia-partial-delivery-modal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; background: rgba(17, 24, 39, 0.55); padding: 24px;"
>
    <div
        style="width: 100%; max-width: 520px; border-radius: 18px; background: #ffffff; padding: 26px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28); border: 1px solid rgba(226, 232, 240, 0.9);"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bexia-partial-delivery-title"
    >
        <div
            id="bexia-partial-delivery-title"
            style="font-size: 18px; line-height: 1.4; font-weight: 800; color: #111827; margin-bottom: 12px;"
        >
            Entrega parcial
        </div>

        <div style="font-size: 14px; line-height: 1.65; color: #475569;">
            Se creará una entrega parcial. Quedará una entrega pendiente relacionada a esta venta.
            ¿Deseas continuar?
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 26px;">
            <button
                type="button"
                id="bexia-partial-delivery-cancel"
                style="border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; padding: 10px 18px; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);"
            >
                Cancelar
            </button>

            <button
                type="button"
                id="bexia-partial-delivery-accept"
                style="border-radius: 10px; border: 1px solid #2563eb; background: #2563eb; color: #ffffff; padding: 10px 18px; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);"
            >
                Aceptar
            </button>
        </div>
    </div>
</div>

<div
    id="bexia-validate-delivery-modal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; background: rgba(17, 24, 39, 0.55); padding: 24px;"
>
    <div
        style="width: 100%; max-width: 520px; border-radius: 18px; background: #ffffff; padding: 26px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28); border: 1px solid rgba(226, 232, 240, 0.9);"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bexia-validate-delivery-title"
    >
        <div
            id="bexia-validate-delivery-title"
            style="font-size: 18px; line-height: 1.4; font-weight: 800; color: #111827; margin-bottom: 12px;"
        >
            Validar entrega
        </div>

        <div style="font-size: 14px; line-height: 1.65; color: #475569;">
            Se validará la entrega, se generará el movimiento de salida y se descontará inventario.
            ¿Deseas continuar?
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 26px;">
            <button
                type="button"
                id="bexia-validate-delivery-cancel"
                style="border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; padding: 10px 18px; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);"
            >
                Cancelar
            </button>

            <button
                type="button"
                id="bexia-validate-delivery-accept"
                style="border-radius: 10px; border: 1px solid #2563eb; background: #2563eb; color: #ffffff; padding: 10px 18px; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);"
            >
                Aceptar
            </button>
        </div>
    </div>
</div>


<div
    id="bexia-delivery-notice-modal"
    style="display: none; position: fixed; inset: 0; z-index: 10000; align-items: center; justify-content: center; background: rgba(17, 24, 39, 0.55); padding: 24px;"
>
    <div
        style="width: 100%; max-width: 480px; border-radius: 18px; background: #ffffff; padding: 24px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28); border: 1px solid rgba(226, 232, 240, 0.9);"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bexia-delivery-notice-title"
    >
        <div
            id="bexia-delivery-notice-title"
            style="font-size: 18px; font-weight: 800; color: #111827; margin-bottom: 8px;"
        >
            Revisa la entrega
        </div>

        <div
            id="bexia-delivery-notice-message"
            style="font-size: 14px; line-height: 1.55; color: #374151; margin-bottom: 20px;"
        >
            Captura al menos una cantidad para entregar.
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button
                type="button"
                id="bexia-delivery-notice-accept"
                style="border: 0; border-radius: 10px; background: #4f46e5; color: white; font-weight: 800; padding: 10px 18px; cursor: pointer;"
            >
                Aceptar
            </button>
        </div>
    </div>
</div>


<script>
(function () {
    function bexiaShowDeliveryNoticeModal(message, title) {
        var modal = document.getElementById('bexia-delivery-notice-modal');

        if (! modal) {
            return false;
        }

        var messageElement = document.getElementById('bexia-delivery-notice-message');
        var titleElement = document.getElementById('bexia-delivery-notice-title');

        if (titleElement) {
            titleElement.textContent = title || 'Revisa la entrega';
        }

        if (messageElement) {
            messageElement.textContent = message || 'Captura al menos una cantidad para entregar.';
        }

        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        var acceptButton = document.getElementById('bexia-delivery-notice-accept');

        if (acceptButton) {
            setTimeout(function () {
                acceptButton.focus();
            }, 30);
        }

        return true;
    }

    function bexiaHideDeliveryNoticeModal() {
        var modal = document.getElementById('bexia-delivery-notice-modal');

        if (! modal) {
            return;
        }

        modal.style.display = 'none';
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('click', function (event) {
        if (
            event.target
            && (
                event.target.id === 'bexia-delivery-notice-accept'
                || event.target.id === 'bexia-delivery-notice-modal'
            )
        ) {
            event.preventDefault();
            bexiaHideDeliveryNoticeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            bexiaHideDeliveryNoticeModal();
        }
    });

    var bexiaPendingValidateDeliveryForm = null;

    function bexiaValidateDeliveryModal() {
        return document.getElementById('bexia-validate-delivery-modal');
    }

    function bexiaShowValidateDeliveryModal(form) {
        bexiaPendingValidateDeliveryForm = form;

        var modal = bexiaValidateDeliveryModal();

        if (! modal) {
            return false;
        }

        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        return true;
    }

    function bexiaHideValidateDeliveryModal() {
        var modal = bexiaValidateDeliveryModal();

        if (! modal) {
            return;
        }

        modal.style.display = 'none';
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    var pendingSubmit = false;

    function deliveryInputs() {
        return Array.prototype.slice.call(document.querySelectorAll('.bexia-delivery-qty'));
    }

    function numberValue(value) {
        var parsed = parseFloat(String(value || '').replace(',', '.'));
        return isNaN(parsed) ? 0 : parsed;
    }

    function pendingValue(input) {
        return numberValue(input.getAttribute('data-pending'));
    }

    function currentState() {
        var inputs = deliveryInputs();
        var any = false;
        var complete = true;

        inputs.forEach(function (input) {
            var pending = pendingValue(input);
            var qty = numberValue(input.value);

            if (qty > 0) {
                any = true;
            }

            if (pending > 0 && Math.abs(qty - pending) > 0.000001) {
                complete = false;
            }
        });

        return {
            any: any,
            complete: complete,
            partial: any && ! complete
        };
    }

    function refreshWarning() {
        var warning = document.getElementById('bexia-partial-warning');

        if (! warning) {
            return;
        }

        var state = currentState();

        if (state.partial) {
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }
    }

    function modalElement() {
        return document.getElementById('bexia-partial-delivery-modal');
    }

    function showPartialModal() {
        var modal = modalElement();

        if (! modal) {
            return false;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.style.display = 'flex';

        return true;
    }

    function hidePartialModal() {
        var modal = modalElement();

        if (! modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.style.display = 'none';
    }

    window.bexiaFillDeliveryQuantities = function () {
        var inputs = deliveryInputs();

        inputs.forEach(function (input) {
            var pending = pendingValue(input);

            if (! input.disabled && pending > 0) {
                input.value = String(pending.toFixed(6)).replace(/\.?0+$/, '');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        refreshWarning();
    };

    window.addEventListener('bexia-fill-delivery-quantities', function () {
        window.bexiaFillDeliveryQuantities();
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        var button = target ? target.closest('[data-bexia-fill-delivery-quantities="1"], a, button') : null;

        if (! button) {
            return;
        }

        var text = (button.textContent || '').trim().toLowerCase();

        if (
            button.getAttribute('data-bexia-fill-delivery-quantities') === '1'
            || text.indexOf('establecer cantidades') !== -1
        ) {
            event.preventDefault();
            event.stopPropagation();
            window.bexiaFillDeliveryQuantities();
            return false;
        }
    }, true);

    document.addEventListener('input', function (event) {
        if (event.target && event.target.classList && event.target.classList.contains('bexia-delivery-qty')) {
            refreshWarning();
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target && event.target.id === 'bexia-partial-delivery-cancel') {
            event.preventDefault();
            pendingSubmit = false;
            hidePartialModal();
        }

        if (event.target && event.target.id === 'bexia-partial-delivery-accept') {
            event.preventDefault();

            var form = document.getElementById('bexia-delivery-form');

            if (! form) {
                return;
            }

            pendingSubmit = true;
            hidePartialModal();
            form.submit();
        }

        if (event.target && event.target.id === 'bexia-partial-delivery-modal') {
            pendingSubmit = false;
            hidePartialModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            pendingSubmit = false;
            hidePartialModal();
        }
    });

    var form = document.getElementById('bexia-delivery-form');
    var createDeliveryButton = document.getElementById('bexia-create-delivery-button');

    if (createDeliveryButton && form) {
        createDeliveryButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (createDeliveryButton.disabled) {
                return false;
            }

            var state = currentState();

            if (! state.any) {
                pendingSubmit = false;
                bexiaShowDeliveryNoticeModal('Captura al menos una cantidad para entregar.', 'Revisa la entrega');
                return false;
            }

            if (state.partial && ! pendingSubmit) {
                showPartialModal();
                return false;
            }

            pendingSubmit = false;
            form.submit();

            return false;
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (! form || form.getAttribute('data-bexia-validate-delivery-form') !== '1') {
            return;
        }

        if (form.getAttribute('data-bexia-validation-confirmed') === '1') {
            form.removeAttribute('data-bexia-validation-confirmed');
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        bexiaShowValidateDeliveryModal(form);
    }, true);

    document.addEventListener('click', function (event) {
        if (event.target && event.target.id === 'bexia-validate-delivery-cancel') {
            event.preventDefault();
            bexiaPendingValidateDeliveryForm = null;
            bexiaHideValidateDeliveryModal();
            return;
        }

        if (event.target && event.target.id === 'bexia-validate-delivery-accept') {
            event.preventDefault();

            if (! bexiaPendingValidateDeliveryForm) {
                bexiaHideValidateDeliveryModal();
                return;
            }

            var form = bexiaPendingValidateDeliveryForm;
            bexiaPendingValidateDeliveryForm = null;

            form.setAttribute('data-bexia-validation-confirmed', '1');
            bexiaHideValidateDeliveryModal();
            form.submit();

            return;
        }

        if (event.target && event.target.id === 'bexia-validate-delivery-modal') {
            event.preventDefault();
            bexiaPendingValidateDeliveryForm = null;
            bexiaHideValidateDeliveryModal();
        }
    });



    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            bexiaHideDeliveryNoticeModal();
        }
    });
    // bexia-delivery-notice-escape-listener

})();
</script>
