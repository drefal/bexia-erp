<x-filament-panels::page>
    @php
        $order = $this->getOrderRow();
        $lines = $this->getLinesForReceipt();
        $hasTracking = collect($lines)->contains(fn ($line) => in_array((string) ($line->tracking_for_view ?? 'none'), ['lot', 'serial'], true));
    @endphp

    <style>
        .bexia-receipt-card {
            background: #fff;
            border: 1px solid #dbe3ef;
            border-radius: 16px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .bexia-receipt-content {
            padding: 20px 24px;
        }

        .bexia-receipt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .bexia-receipt-table th {
            background: #f8fafc;
            border-bottom: 1px solid #dbe3ef;
            padding: 10px;
            text-align: left;
            font-weight: 800;
        }

        .bexia-receipt-table td {
            border-bottom: 1px solid #edf2f7;
            padding: 10px;
            vertical-align: top;
        }

        .bexia-receipt-card input,
        .bexia-receipt-card textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 10px;
            min-height: 38px;
            outline: none;
            box-sizing: border-box;
            background: white;
        }

        .bexia-receipt-card input:focus,
        .bexia-receipt-card textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .qty {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .receive-input {
            max-width: 150px;
            text-align: right;
        }

        .tracking-cell {
            min-width: 260px;
        }

        .tracking-box {
            display: grid;
            gap: 8px;
        }

        .tracking-label {
            display: inline-flex;
            width: fit-content;
            border-radius: 999px;
            padding: 4px 9px;
            font-weight: 800;
            font-size: 12px;
            background: #eef2ff;
            color: #3730a3;
        }

        .tracking-label.lot {
            background: #ecfdf5;
            color: #065f46;
        }

        .tracking-label.serial {
            background: #fff7ed;
            color: #9a3412;
        }

        .help {
            color: #64748b;
            font-size: 12px;
            line-height: 1.35;
        }

        .notice {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            min-height: 42px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            padding: 0 16px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: white;
            box-shadow: 0 12px 22px rgba(37, 99, 235, .25);
        }

        .btn-gray {
            background: white;
            color: #334155;
        }

        .btn-small {
            min-height: 34px;
            border-radius: 10px;
            padding: 0 12px;
            font-size: 12px;
        }
    </style>

    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    <div class="bexia-receipt-card">
        <form method="POST" action="{{ $this->postActionUrl() }}">
            @csrf

            <div class="bexia-receipt-content">
                <div class="notice">
                    @if($hasTracking)
                        Solo las líneas de productos configurados con seguimiento por lote o número de serie pedirán datos adicionales.
                        Los productos sin seguimiento se reciben igual que antes.
                    @else
                        Esta orden no contiene productos con lote o número de serie. La recepción se capturará solo por cantidad.
                    @endif
                </div>

                <table class="bexia-receipt-table">
                    <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Variante</th>
                        <th>Unidad</th>
                        <th class="qty">Ordenado</th>
                        <th class="qty">Recibido</th>
                        <th class="qty">Pendiente</th>
                        <th class="qty">A recibir</th>
                        @if($hasTracking)
                            <th>Seguimiento</th>
                        @endif
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($lines as $line)
                        @php
                            $tracking = (string) ($line->tracking_for_view ?? 'none');
                            $lineId = (int) $line->id;
                        @endphp

                        <tr>
                            <td>
                                <strong>{{ $line->product_label ?? 'Producto' }}</strong>
                            </td>
                            <td>{{ $line->variant_label ?? '—' }}</td>
                            <td>{{ $line->purchase_unit_label ?? 'Unidad' }}</td>
                            <td class="qty">{{ number_format((float) $line->ordered_for_view, 6) }}</td>
                            <td class="qty">{{ number_format((float) $line->received_for_view, 6) }}</td>
                            <td class="qty">{{ number_format((float) $line->pending_for_view, 6) }}</td>
                            <td class="qty">
                                <input
                                    class="receive-input"
                                    type="number"
                                    step="0.000001"
                                    min="0"
                                    max="{{ $line->pending_for_view }}"
                                    name="quantities[{{ $lineId }}]"
                                    value="{{ old('quantities.' . $lineId, $line->pending_for_view > 0 ? number_format((float) $line->pending_for_view, 6, '.', '') : '0') }}"
                                    @disabled($line->pending_for_view <= 0)
                                >
                            </td>

                            @if($hasTracking)
                                <td class="tracking-cell">
                                    @if($tracking === 'lot')
                                        <div class="tracking-box">
                                            <span class="tracking-label lot">Lote</span>

                                            <input
                                                type="text"
                                                name="lot_numbers[{{ $lineId }}]"
                                                value="{{ old('lot_numbers.' . $lineId) }}"
                                                placeholder="Número de lote"
                                                @disabled($line->pending_for_view <= 0)
                                            >

                                            <input
                                                type="date"
                                                name="lot_expiration_dates[{{ $lineId }}]"
                                                value="{{ old('lot_expiration_dates.' . $lineId) }}"
                                                @disabled($line->pending_for_view <= 0)
                                            >

                                            <div class="help">
                                                El lote solo es obligatorio si la cantidad a recibir es mayor a cero.
                                            </div>
                                        </div>
                                    @elseif($tracking === 'serial')
                                        <div class="tracking-box">
                                            <span class="tracking-label serial">Número de serie</span>

                                            <textarea
                                                name="serial_numbers[{{ $lineId }}]"
                                                rows="4"
                                                placeholder="Un número de serie por línea"
                                                @disabled($line->pending_for_view <= 0)
                                            >{{ old('serial_numbers.' . $lineId) }}</textarea>

                                            <div class="help">
                                                Captura un número de serie por unidad recibida. También puedes separar por coma.
                                            </div>
                                        </div>
                                    @else
                                        <span class="help">Sin seguimiento</span>
                                    @endif
                                </td>
                            @endif

                            <td>
                                <button
                                    type="button"
                                    class="btn btn-gray btn-small"
                                    onclick="this.closest('tr').querySelector('.receive-input').value='0'"
                                >
                                    No recibir
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div style="margin-top:18px;">
                    <label style="font-weight:800; display:block; margin-bottom:6px;">Notas de recepción</label>
                    <textarea name="notes" rows="3" placeholder="Notas opcionales de recepción">{{ old('notes') }}</textarea>
                </div>

                <div class="actions">
                    <a class="btn btn-gray" href="{{ $this->cancelUrl() }}">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Guardar recepción
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page>
