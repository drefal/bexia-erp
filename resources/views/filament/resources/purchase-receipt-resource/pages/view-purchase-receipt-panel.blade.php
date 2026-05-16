<x-filament-panels::page>
    @php
        $receipt = $this->receipt();
        $order = $this->order();
        $movement = $this->movement();
        $warehouse = $this->warehouse();
        $location = $this->location();
        $receivedBy = $this->receivedBy();
        $lines = $this->lines();

        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $qty = fn ($v) => number_format((float) $v, 6);

        $serialValuesForLine = function ($line): array {
            $raw = $line->serial_numbers ?? null;

            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                $values = is_array($decoded) ? $decoded : preg_split('/[\r\n,;]+/', $raw);
            } elseif (is_array($raw)) {
                $values = $raw;
            } else {
                $values = [];
            }

            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values)));
        };
    @endphp

    <style>
        .bexia-receipt-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 18px;
        }

        .bexia-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 40px;
            border-radius: 12px;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
        }

        .bexia-btn:hover {
            background: #f8fafc;
        }

        .bexia-btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 12px 22px rgba(37, 99, 235, 0.25);
        }

        .bexia-btn-primary:hover {
            background: #1d4ed8;
        }

        .bexia-receipt-card {
            overflow: hidden;
            border-radius: 18px;
            border: 1px solid #dbe3ef;
            background: #ffffff;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06);
        }

        .bexia-receipt-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 24px 28px;
            border-bottom: 1px solid #e5e7eb;
        }

        .bexia-receipt-title {
            font-size: 26px;
            line-height: 1.2;
            font-weight: 900;
            color: #020617;
        }

        .bexia-receipt-muted {
            margin-top: 6px;
            font-size: 14px;
            color: #64748b;
        }

        .bexia-status {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 12px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
            font-weight: 900;
        }

        .bexia-info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 22px 28px;
            border-bottom: 1px solid #e5e7eb;
        }

        .bexia-info-card {
            min-height: 74px;
            border-radius: 14px;
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            padding: 13px 15px;
        }

        .bexia-info-label {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
        }

        .bexia-info-value {
            margin-top: 8px;
            font-size: 16px;
            font-weight: 900;
            color: #020617;
        }

        .bexia-table-wrap {
            overflow-x: auto;
        }

        .bexia-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .bexia-table th {
            background: #f8fafc;
            border-bottom: 1px solid #dbe3ef;
            padding: 13px 15px;
            text-align: left;
            font-weight: 900;
            color: #334155;
            white-space: nowrap;
        }

        .bexia-table td {
            border-bottom: 1px solid #edf2f7;
            padding: 15px;
            vertical-align: top;
            color: #0f172a;
        }

        .bexia-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .bexia-tracking-empty {
            color: #94a3b8;
            font-size: 13px;
        }

        .bexia-lot {
            display: inline-flex;
            border-radius: 999px;
            border: 1px solid #a7f3d0;
            background: #ecfdf5;
            padding: 5px 9px;
            font-size: 13px;
            font-weight: 900;
            color: #047857;
        }

        .bexia-serial-details {
            max-width: 360px;
            border-radius: 12px;
            border: 1px solid #fed7aa;
            background: #fff7ed;
            padding: 8px 10px;
        }

        .bexia-serial-details summary {
            cursor: pointer;
            font-size: 13px;
            font-weight: 900;
            color: #9a3412;
        }

        .bexia-serial-list {
            max-height: 180px;
            overflow-y: auto;
            margin-top: 8px;
        }

        .bexia-serial-chip {
            display: inline-flex;
            margin: 2px 4px 2px 0;
            border-radius: 999px;
            border: 1px solid #fed7aa;
            background: #ffffff;
            padding: 4px 9px;
            font-size: 13px;
            font-weight: 800;
            color: #9a3412;
        }

        .bexia-totals-row {
            display: flex;
            justify-content: flex-end;
            padding: 22px 28px 28px;
        }

        .bexia-totals {
            width: 100%;
            max-width: 360px;
            border-radius: 14px;
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            padding: 14px 16px;
        }

        .bexia-total-line {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 6px 0;
            font-size: 14px;
        }

        .bexia-grand-total {
            margin-top: 8px;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
            font-size: 18px;
            font-weight: 900;
        }

        @media (max-width: 1100px) {
            .bexia-info-grid {
                grid-template-columns: 1fr;
            }

            .bexia-receipt-header {
                flex-direction: column;
            }
        }
    </style>

    <div class="bexia-receipt-actions">
        <a href="{{ $this->ocUrl() }}" class="bexia-btn">
            Ver OC
        </a>

        <a href="{{ $this->movementUrl() }}" class="bexia-btn">
            Ver movimiento
        </a>

        <a href="{{ $this->printUrl() }}" target="_blank" rel="noopener" class="bexia-btn bexia-btn-primary">
            Imprimir
        </a>
    </div>

    <div class="bexia-receipt-card">
        <div class="bexia-receipt-header">
            <div>
                <div class="bexia-receipt-title">
                    Recepción de compra
                </div>
                <div class="bexia-receipt-muted">
                    {{ $order->supplier_name ?? $receipt->supplier_name ?? 'Proveedor' }}
                </div>
            </div>

            <div style="text-align:right;">
                <div class="bexia-receipt-title">{{ $receipt->number ?? ('#' . $receipt->id) }}</div>
                <div style="margin-top:10px;">
                    <span class="bexia-status">Recibida</span>
                </div>
            </div>
        </div>

        <div class="bexia-info-grid">
            <div class="bexia-info-card">
                <div class="bexia-info-label">Orden de compra</div>
                <div class="bexia-info-value">{{ $order->number ?? '—' }}</div>
            </div>

            <div class="bexia-info-card">
                <div class="bexia-info-label">Movimiento inventario</div>
                <div class="bexia-info-value">{{ $movement->reference ?? '—' }}</div>
            </div>

            <div class="bexia-info-card">
                <div class="bexia-info-label">Fecha recepción</div>
                <div class="bexia-info-value">
                    {{ $receipt->received_at ? \Carbon\Carbon::parse($receipt->received_at)->format('d/m/Y H:i') : '—' }}
                </div>
            </div>

            <div class="bexia-info-card">
                <div class="bexia-info-label">Almacén</div>
                <div class="bexia-info-value">{{ $warehouse->name ?? '—' }}</div>
            </div>

            <div class="bexia-info-card">
                <div class="bexia-info-label">Ubicación</div>
                <div class="bexia-info-value">{{ $location->name ?? '—' }}</div>
            </div>

            <div class="bexia-info-card">
                <div class="bexia-info-label">Recibió</div>
                <div class="bexia-info-value">{{ $receivedBy->name ?? $receivedBy->email ?? '—' }}</div>
            </div>
        </div>

        <div class="bexia-table-wrap">
            <table class="bexia-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Variante</th>
                        <th>Seguimiento</th>
                        <th>Unidad</th>
                        <th class="bexia-num">Cantidad</th>
                        <th class="bexia-num">Costo s/IVA</th>
                        <th class="bexia-num">IVA</th>
                        <th class="bexia-num">Total</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($lines as $line)
                        @php
                            $trackingType = (string) ($line->tracking_type ?? 'none');
                            $serialValues = $serialValuesForLine($line);
                            $shownSerials = array_slice($serialValues, 0, 20);
                            $hiddenSerialCount = max(count($serialValues) - count($shownSerials), 0);
                        @endphp

                        <tr>
                            <td><strong>{{ $line->product_label ?? 'Producto' }}</strong></td>
                            <td>{{ $line->variant_label ?? '—' }}</td>
                            <td>
                                @if($trackingType === 'lot' && ! empty($line->lot_number))
                                    <span class="bexia-lot">Lote: {{ $line->lot_number }}</span>

                                    @if(! empty($line->lot_expiration_date))
                                        <div style="margin-top:6px; font-size:13px; color:#64748b;">
                                            Caducidad: {{ \Carbon\Carbon::parse($line->lot_expiration_date)->format('d/m/Y') }}
                                        </div>
                                    @endif
                                @elseif($trackingType === 'serial' && count($serialValues))
                                    <details class="bexia-serial-details">
                                        <summary>{{ count($serialValues) }} número(s) de serie</summary>

                                        <div class="bexia-serial-list">
                                            @foreach($shownSerials as $serial)
                                                <span class="bexia-serial-chip">{{ $serial }}</span>
                                            @endforeach

                                            @if($hiddenSerialCount > 0)
                                                <div style="margin-top:8px; font-size:13px; font-weight:800; color:#9a3412;">
                                                    +{{ $hiddenSerialCount }} serie(s) más
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                @else
                                    <span class="bexia-tracking-empty">Sin seguimiento</span>
                                @endif
                            </td>
                            <td>{{ $line->purchase_unit_label ?? '—' }}</td>
                            <td class="bexia-num">{{ $qty($line->received_quantity ?? 0) }}</td>
                            <td class="bexia-num">{{ $money($line->unit_cost_without_tax ?? 0) }}</td>
                            <td class="bexia-num">{{ $money($line->line_tax ?? 0) }}</td>
                            <td class="bexia-num"><strong>{{ $money($line->line_total_with_tax ?? 0) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bexia-totals-row">
            <div class="bexia-totals">
                <div class="bexia-total-line">
                    <span>Importe sin impuestos</span>
                    <strong>{{ $money($receipt->total_without_tax ?? 0) }}</strong>
                </div>

                <div class="bexia-total-line">
                    <span>IVA</span>
                    <strong>{{ $money($receipt->total_tax ?? 0) }}</strong>
                </div>

                <div class="bexia-total-line bexia-grand-total">
                    <span>Total</span>
                    <strong>{{ $money($receipt->total_with_tax ?? 0) }}</strong>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
