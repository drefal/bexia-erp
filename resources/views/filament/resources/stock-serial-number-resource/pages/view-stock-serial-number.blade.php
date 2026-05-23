<x-filament-panels::page>
    @php
        $serial = $this->serial();
        $product = $this->product();
        $variant = $this->variant();
        $lot = $this->lot();
        $receipt = $this->receipt();
        $receiptLine = $this->receiptLine();
        $movement = $this->movement();
        $warehouse = $this->warehouse();
        $location = $this->location();

        $order = $this->posOrder();
        $orderLine = $this->posOrderLine();
        $outMovement = $this->outboundMovement();
        $outLine = $this->outboundMovementLine();
        $history = $this->movementHistory();

        $value = fn ($v) => filled($v ?? null) ? $v : '—';

        $statusLabels = [
            'available' => 'Disponible',
            'in_stock' => 'Disponible',
            'active' => 'Activo',
            'received' => 'Recibido',
            'reserved' => 'Reservado',
            'sold' => 'Vendido',
            'used' => 'Usado',
            'consumed' => 'Consumido',
            'inactive' => 'Inactivo',
            'blocked' => 'Bloqueado',
            'quarantine' => 'En cuarentena',
            'quarantined' => 'En cuarentena',
            'damaged' => 'Dañado',
            'expired' => 'Caducado',
            'done' => 'Hecho',
            'draft' => 'Borrador',
            'pending' => 'Pendiente',            'delivered' => 'Entregado',            'returned' => 'Devuelto',            'scrapped' => 'Merma / desecho',            'lost' => 'Perdido',




        ];

        $statusText = function ($status) use ($statusLabels): string {
            $key = mb_strtolower(trim((string) ($status ?? '')));

            if ($key === '') {
                return '—';
            }

            return $statusLabels[$key] ?? ($key !== '' ? ucfirst(str_replace('_', ' ', $key)) : 'Sin estado');
        };

        $productLabel = trim(implode(' - ', array_filter([
            $product->internal_reference ?? $product->sku ?? null,
            $product->name ?? null,
        ]))) ?: '—';

        $variantLabel = $variant->name
            ?? $variant->variant_name
            ?? $receiptLine->variant_label
            ?? null;

        $lotNumber = $lot->lot_number
            ?? $lot->number
            ?? $serial->lot_number
            ?? '—';
    @endphp

    <style>
        .bexia-detail-actions{display:flex;justify-content:flex-end;gap:10px;margin-bottom:18px}
        .bexia-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:12px;padding:0 16px;font-size:14px;font-weight:800;text-decoration:none;border:1px solid #cbd5e1;background:#fff;color:#0f172a}
        .bexia-btn-primary{border-color:#2563eb;background:#2563eb;color:#fff}
        .bexia-card{border:1px solid #dbe3ef;background:#fff;border-radius:18px;box-shadow:0 12px 26px rgba(15,23,42,.06);overflow:hidden;margin-bottom:24px}
        .bexia-card-header{padding:22px 26px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
        .bexia-title{font-size:22px;font-weight:850;color:#020617}
        .bexia-subtitle{margin-top:6px;font-size:14px;color:#64748b}
        .bexia-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;padding:24px 26px}
        .bexia-field{border:1px solid #dbe3ef;border-radius:14px;background:#fbfdff;padding:15px 16px;min-height:82px}
        .bexia-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .bexia-value{margin-top:9px;font-size:15px;font-weight:650;color:#020617;word-break:break-word}
        .bexia-table-wrap{padding:24px 26px;overflow-x:auto}
        .bexia-table{width:100%;border-collapse:collapse;font-size:14px}
        .bexia-table th{background:#f8fafc;border-bottom:1px solid #dbe3ef;padding:13px 14px;text-align:left;font-weight:800;color:#334155}
        .bexia-table td{border-bottom:1px solid #edf2f7;padding:13px 14px;vertical-align:top;color:#0f172a}
        .bexia-table .right{text-align:right}
        @media(max-width:1100px){.bexia-grid{grid-template-columns:1fr}}
    </style>

    <div class="bexia-detail-actions">
        <a href="{{ $this->printUrl() }}" target="_blank" rel="noopener" class="bexia-btn">
            Imprimir
        </a>

        @if($this->receiptUrl())
            <a href="{{ $this->receiptUrl() }}" class="bexia-btn">Ver recepción</a>
        @endif

        @if($this->lotUrl())
            <a href="{{ $this->lotUrl() }}" class="bexia-btn">Ver lote</a>
        @endif
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">{{ $value($serial->serial_number ?? null) }}</div>
            <div class="bexia-subtitle">Detalle del número de serie. Los datos de compra, ubicación e importación se muestran en las secciones inferiores.</div>
        </div>

        <div class="bexia-grid">
            <div class="bexia-field">
                <div class="bexia-label">Producto</div>
                <div class="bexia-value">{{ $productLabel }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Variante</div>
                <div class="bexia-value">{{ $value($variantLabel) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Estado</div>
                <div class="bexia-value">{{ $statusText($serial->status ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Lote</div>
                <div class="bexia-value">{{ $lotNumber }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Recepción</div>
                <div class="bexia-value">{{ $value($receipt->number ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Movimiento</div>
                <div class="bexia-value">{{ $value($movement->reference ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Almacén</div>
                <div class="bexia-value">{{ $value($warehouse->name ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Ubicación</div>
                <div class="bexia-value">{{ $value($location->name ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Cantidad</div>
                <div class="bexia-value">1</div>
            </div>
        </div>
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">Venta / salida relacionada</div>
            <div class="bexia-subtitle">Documento que vendió o sacó este número de serie del inventario.</div>
        </div>

        <div class="bexia-grid">
            <div class="bexia-field">
                <div class="bexia-label">Documento salida</div>
                <div class="bexia-value">{{ $this->sourceLabel($serial->out_source_type ?? null, $serial->out_source_id ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Línea salida</div>
                <div class="bexia-value">{{ $this->sourceLabel($serial->out_source_line_type ?? null, $serial->out_source_line_id ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Fecha venta/salida</div>
                <div class="bexia-value">{{ $this->formatDateTime($serial->sold_at ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Ticket PDV</div>
                <div class="bexia-value">{{ $order->number ?? ($order ? ('#' . $order->id) : '—') }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Cliente</div>
                <div class="bexia-value">{{ $order ? $this->contactLabel($order->customer_id ?? null) : '—' }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Punto de venta</div>
                <div class="bexia-value">{{ $order ? $this->posPointLabel($order->pos_point_id ?? null) : '—' }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Vendido por</div>
                <div class="bexia-value">{{ $this->userLabel($serial->sold_by ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Movimiento salida</div>
                <div class="bexia-value">{{ $outMovement->reference ?? ($outMovement ? ('#' . $outMovement->id) : '—') }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Línea inventario salida</div>
                <div class="bexia-value">{{ $outLine ? ('#' . $outLine->id) : '—' }}</div>
            </div>
        </div>

        @if($orderLine)
            <div class="bexia-table-wrap">
                <table class="bexia-table">
                    <thead>
                        <tr>
                            <th>Línea PDV</th>
                            <th>Producto</th>
                            <th class="right">Cantidad</th>
                            <th class="right">Precio</th>
                            <th class="right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#{{ $orderLine->id }}</td>
                            <td>{{ $orderLine->product_reference ?? '—' }} - {{ $orderLine->product_name ?? '—' }}</td>
                            <td class="right">{{ $this->formatNumber($orderLine->quantity ?? null) }}</td>
                            <td class="right">{{ $this->formatNumber($orderLine->unit_price ?? null) }}</td>
                            <td class="right">{{ $this->formatNumber($orderLine->total ?? null) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">Trazabilidad / Importación</div>
            <div class="bexia-subtitle">Datos capturados en la recepción para este número de serie.</div>
        </div>

        <div class="bexia-grid">
            <div class="bexia-field">
                <div class="bexia-label">Número de motor</div>
                <div class="bexia-value">{{ $value($serial->motor_number ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Número de pedimento</div>
                <div class="bexia-value">{{ $value($serial->customs_entry_number ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Fecha pedimento</div>
                <div class="bexia-value">{{ $value($serial->customs_entry_date ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Aduana</div>
                <div class="bexia-value">{{ $value($serial->customs_office ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Modelo importado</div>
                <div class="bexia-value">{{ $value($serial->imported_model ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Color importado</div>
                <div class="bexia-value">{{ $value($serial->imported_color ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Referencia documento/factura</div>
                <div class="bexia-value">{{ $value($serial->import_document_reference ?? null) }}</div>
            </div>
        </div>
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">Historial de movimientos</div>
            <div class="bexia-subtitle">Entradas y salidas de inventario relacionadas con este número de serie.</div>
        </div>

        <div class="bexia-table-wrap">
            <table class="bexia-table">
                <thead>
                    <tr>
                        <th>Línea</th>
                        <th>Movimiento</th>
                        <th>Fecha</th>
                        <th class="right">Cantidad</th>
                        <th>Origen</th>
                        <th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        <tr>
                            <td>#{{ $row->id }}</td>
                            <td>{{ $row->movement_reference ?? ('#' . ($row->stock_movement_id ?? '—')) }}</td>
                            <td>{{ $this->formatDateTime($row->movement_at ?? $row->created_at ?? null) }}</td>
                            <td class="right">{{ $this->formatNumber($row->done_quantity ?? $row->requested_quantity ?? null) }}</td>
                            <td>{{ $this->sourceLabel($row->source_type ?? null, $row->source_id ?? null) }}</td>
                            <td>{{ $row->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;color:#64748b;padding:18px">Sin movimientos relacionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
