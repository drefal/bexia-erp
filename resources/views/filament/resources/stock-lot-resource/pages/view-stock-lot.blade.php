<x-filament-panels::page>
    @php
        $lot = $this->lot();
        $sales = $this->saleDeliveryLines();
        $movements = $this->movements();
        $serials = $this->serials();
        $quants = $this->quants();

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
            'done' => 'Validada',
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
            'cancelled' => 'Cancelada',
        ];

        $statusText = function ($status) use ($statusLabels): string {
            $key = mb_strtolower(trim((string) ($status ?? '')));

            if ($key === '') {
                return '—';
            }

            return $statusLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
        };
    @endphp

    <style>
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
        .bexia-empty{text-align:center;color:#64748b;padding:18px}
        @media(max-width:1100px){.bexia-grid{grid-template-columns:1fr}}
    </style>

    @if (! $lot)
        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">Lote no encontrado</div>
                <div class="bexia-subtitle">No se encontró el lote solicitado.</div>
            </div>
        </div>
    @else
        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">{{ $value($lot->lot_number ?? null) }}</div>
                <div class="bexia-subtitle">Detalle del lote, existencias y movimientos relacionados.</div>
            </div>

            <div class="bexia-grid">
                <div class="bexia-field">
                    <div class="bexia-label">Producto</div>
                    <div class="bexia-value">{{ $this->productLabel($lot->product_id ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Variante</div>
                    <div class="bexia-value">{{ $this->productLabel($lot->product_variant_id ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Estado</div>
                    <div class="bexia-value">{{ $statusText($lot->status ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Caducidad</div>
                    <div class="bexia-value">{{ $this->d($lot->expiration_date ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Proveedor</div>
                    <div class="bexia-value">{{ $this->labelFromTable('contacts', $lot->supplier_contact_id ?? null, ['name', 'business_name', 'legal_name', 'rfc']) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Recepción compra</div>
                    <div class="bexia-value">{{ ! empty($lot->purchase_receipt_id) ? ('#' . $lot->purchase_receipt_id) : '—' }}</div>
                </div>
            </div>
        </div>

        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">Ventas / salidas del lote</div>
                <div class="bexia-subtitle">Entregas de venta y salidas relacionadas directamente a este lote.</div>
            </div>

            <div class="bexia-table-wrap">
                <table class="bexia-table">
                    <thead>
                        <tr>
                            <th>Entrega</th>
                            <th>Orden</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th class="right">Cantidad</th>
                            <th>Movimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $row)
                            <tr>
                                <td>{{ $row->delivery_number ?? ('Entrega #' . ($row->sale_delivery_id ?? '—')) }}</td>
                                <td>{{ $row->sale_order_number ?? ('Orden #' . ($row->sales_order_id ?? '—')) }}</td>
                                <td>{{ $row->customer_name ?? '—' }}</td>
                                <td>{{ $statusText($row->delivery_status ?? null) }}</td>
                                <td>{{ $this->dt($row->delivery_delivered_at ?? $row->delivery_created_at ?? $row->created_at ?? null) }}</td>
                                <td class="right">{{ $this->n($row->quantity ?? null) }}</td>
                                <td>
                                    @if(! empty($row->movement_line_id))
                                        Línea #{{ $row->movement_line_id }}
                                    @elseif(! empty($row->stock_movement_line_id))
                                        Línea #{{ $row->stock_movement_line_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="bexia-empty">
                                    No hay ventas o salidas ligadas a este lote.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">Existencias por ubicación</div>
                <div class="bexia-subtitle">Existencia actual del lote por almacén y ubicación.</div>
            </div>

            <div class="bexia-table-wrap">
                <table class="bexia-table">
                    <thead>
                        <tr>
                            <th>Quant</th>
                            <th>Almacén</th>
                            <th>Ubicación</th>
                            <th class="right">Cantidad</th>
                            <th class="right">Reservado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quants as $row)
                            <tr>
                                <td>#{{ $row->id }}</td>
                                <td>{{ $this->labelFromTable('warehouses', $row->warehouse_id ?? null, ['name', 'code']) }}</td>
                                <td>{{ $this->labelFromTable('stock_locations', $row->location_id ?? $row->stock_location_id ?? null, ['name', 'code']) }}</td>
                                <td class="right">{{ $this->n($row->quantity ?? $row->on_hand_quantity ?? null) }}</td>
                                <td class="right">{{ $this->n($row->reserved_quantity ?? null) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="bexia-empty">Sin existencias por ubicación.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">Historial de movimientos del lote</div>
                <div class="bexia-subtitle">Movimientos de inventario ligados a stock_movement_lines.lot_id.</div>
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
                        @forelse($movements as $row)
                            <tr>
                                <td>#{{ $row->id }}</td>
                                <td>{{ $row->movement_reference ?? ('#' . ($row->stock_movement_id ?? '—')) }}</td>
                                <td>{{ $this->dt($row->movement_at ?? $row->created_at ?? null) }}</td>
                                <td class="right">{{ $this->n($row->done_quantity ?? $row->requested_quantity ?? null) }}</td>
                                <td>{{ $this->sourceLabel($row->source_type ?? null, $row->source_id ?? null) }}</td>
                                <td>{{ $row->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="bexia-empty">Sin movimientos ligados al lote.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">Series dentro del lote</div>
                <div class="bexia-subtitle">Números de serie ligados a este lote, si aplica.</div>
            </div>

            <div class="bexia-table-wrap">
                <table class="bexia-table">
                    <thead>
                        <tr>
                            <th>Serie</th>
                            <th>Estatus</th>
                            <th>Salida</th>
                            <th>Fecha venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serials as $row)
                            <tr>
                                <td>{{ $row->serial_number ?? '—' }}</td>
                                <td>{{ $statusText($row->status ?? null) }}</td>
                                <td>{{ $this->sourceLabel($row->out_source_type ?? null, $row->out_source_id ?? null) }}</td>
                                <td>{{ $this->dt($row->sold_at ?? null) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="bexia-empty">Sin series ligadas al lote.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
