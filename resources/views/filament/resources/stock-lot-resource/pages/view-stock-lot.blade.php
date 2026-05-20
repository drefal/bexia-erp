<x-filament-panels::page>
    @php
        $lot = $this->lot();
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
            'done' => 'Hecho',
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
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
                <div class="bexia-subtitle">Detalle del lote. Los datos de compra, importación, existencias y movimientos se muestran en las secciones inferiores.</div>
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
                <div class="bexia-title">Trazabilidad / Importación</div>
                <div class="bexia-subtitle">Datos capturados en la recepción para este lote.</div>
            </div>

            <div class="bexia-grid">
                <div class="bexia-field">
                    <div class="bexia-label">Número de motor</div>
                    <div class="bexia-value">{{ $value($lot->motor_number ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Número de pedimento</div>
                    <div class="bexia-value">{{ $value($lot->customs_entry_number ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Fecha pedimento</div>
                    <div class="bexia-value">{{ $this->d($lot->customs_entry_date ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Aduana</div>
                    <div class="bexia-value">{{ $value($lot->customs_office ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Modelo importado</div>
                    <div class="bexia-value">{{ $value($lot->imported_model ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Color importado</div>
                    <div class="bexia-value">{{ $value($lot->imported_color ?? null) }}</div>
                </div>
            </div>
        </div>

        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">Historial de movimientos del lote</div>
                <div class="bexia-subtitle">Entradas y salidas ligadas a stock_movement_lines.lot_id.</div>
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
                                <td colspan="6" style="text-align:center;color:#64748b;padding:18px">Sin movimientos ligados al lote.</td>
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quants as $row)
                            <tr>
                                <td>#{{ $row->id }}</td>
                                <td>{{ $this->labelFromTable('warehouses', $row->warehouse_id ?? null, ['name', 'code']) }}</td>
                                <td>{{ $this->labelFromTable('stock_locations', $row->location_id ?? $row->stock_location_id ?? null, ['name', 'code']) }}</td>
                                <td class="right">{{ $this->n($row->quantity ?? $row->on_hand_quantity ?? $row->available_quantity ?? null) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center;color:#64748b;padding:18px">Sin existencias por ubicación.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bexia-card">
            <div class="bexia-card-header">
                <div class="bexia-title">Series dentro del lote</div>
                <div class="bexia-subtitle">Números de serie ligados a este lote.</div>
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
                                <td colspan="4" style="text-align:center;color:#64748b;padding:18px">Sin series ligadas al lote.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
