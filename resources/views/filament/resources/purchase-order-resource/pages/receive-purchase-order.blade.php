<x-filament-panels::page>
    @php
        $order = $this->getOrderRow();
        $lines = $this->getLinesForReceipt();
        $hasTracking = collect($lines)->contains(fn ($line) => in_array((string) ($line->tracking_for_view ?? 'none'), ['lot', 'serial'], true));
        $hasAdvancedTracking = collect($lines)->contains(fn ($line) => in_array((string) ($line->advanced_tracking_mode_for_view ?? 'none'), ['warning', 'required'], true));
        $showTrackingColumn = $hasTracking || $hasAdvancedTracking;
        $importFieldLabels = [
            'motor_number' => 'Número de motor',
            'customs_entry_number' => 'Número de pedimento',
            'customs_entry_date' => 'Fecha de pedimento',
            'customs_office' => 'Aduana',
            'imported_model' => 'Modelo importado',
            'imported_color' => 'Color importado',
            'import_document_reference' => 'Referencia documento',
        ];
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


        .import-common {
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .import-common-title {
            font-weight: 900;
            margin-bottom: 4px;
        }

        .import-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .import-field label,
        .line-import-field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .apply-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-size: 13px;
            font-weight: 800;
        }

        .apply-row input {
            width: auto;
            min-height: auto;
        }

        .line-import-box {
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 10px;
            margin-top: 8px;
            background: #fbfdff;
        }

        .line-import-title {
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .line-import-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .line-import-grid textarea {
            min-height: 70px;
        }

        .advanced-badge {
            display: inline-flex;
            width: fit-content;
            border-radius: 999px;
            padding: 3px 8px;
            font-weight: 900;
            font-size: 11px;
            background: #fef3c7;
            color: #92400e;
        }

        .advanced-badge.required {
            background: #fee2e2;
            color: #991b1b;
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
                    @if($hasTracking || $hasAdvancedTracking)
                        Las líneas con lote, número de serie o trazabilidad avanzada pedirán datos adicionales.
                        Si el producto tiene trazabilidad obligatoria, la recepción se bloqueará cuando falten datos.
                    @else
                        Esta orden no contiene productos con lote, número de serie ni trazabilidad avanzada. La recepción se capturará solo por cantidad.
                    @endif
                </div>

                @if($hasAdvancedTracking)
                    <div class="import-common">
                        <div class="import-common-title">Datos comunes de importación</div>
                        <div class="help">
                            Si todos los productos de esta recepción comparten pedimento, fecha o aduana, captura los datos aquí y se copiarán a todas las líneas que no tengan valor propio.
                        </div>

                        <div class="import-grid">
                            <div class="import-field">
                                <label>Número de pedimento</label>
                                <input type="text" name="common_import_data[customs_entry_number]" value="{{ old('common_import_data.customs_entry_number') }}" placeholder="Ej. 26 16 1663 6000377">
                            </div>

                            <div class="import-field">
                                <label>Fecha de pedimento</label>
                                <input type="date" name="common_import_data[customs_entry_date]" value="{{ old('common_import_data.customs_entry_date') }}">
                            </div>

                            <div class="import-field">
                                <label>Aduana</label>
                                <input type="text" name="common_import_data[customs_office]" value="{{ old('common_import_data.customs_office') }}" placeholder="Ej. MANZANILLO">
                            </div>

                            <div class="import-field">
                                <label>Referencia documento/factura</label>
                                <input type="text" name="common_import_data[import_document_reference]" value="{{ old('common_import_data.import_document_reference') }}" placeholder="Factura, XML o referencia">
                            </div>
                        </div>

                        <label class="apply-row">
                            <input type="checkbox" name="apply_common_import_to_all" value="1" @checked(old('apply_common_import_to_all', '1') === '1')>
                            Aplicar estos datos a todas las líneas que no tengan datos propios.
                        </label>
                    </div>
                @endif

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
                        @if($showTrackingColumn)
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
                            $advancedMode = (string) ($line->advanced_tracking_mode_for_view ?? 'none');
                            $advancedFields = (array) ($line->advanced_tracking_fields_for_view ?? []);
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

                            @if($showTrackingColumn)
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

                                    @if(in_array($advancedMode, ['warning', 'required'], true))
                                        <div class="line-import-box">
                                            <div class="line-import-title">
                                                Datos de importación
                                                <span class="advanced-badge {{ $advancedMode === 'required' ? 'required' : '' }}">
                                                    {{ $advancedMode === 'required' ? 'Obligatorio' : 'Aviso' }}
                                                </span>
                                            </div>

                                            <div class="line-import-grid">
                                                @foreach($advancedFields as $fieldName)
                                                    @continue($fieldName === 'serial_number')

                                                    @php
                                                        $label = $importFieldLabels[$fieldName] ?? $fieldName;
                                                        $oldKey = 'line_import_data.' . $lineId . '.' . $fieldName;
                                                    @endphp

                                                    <div class="line-import-field">
                                                        <label>{{ $label }}</label>

                                                        @if($fieldName === 'customs_entry_date')
                                                            <input
                                                                type="date"
                                                                name="line_import_data[{{ $lineId }}][{{ $fieldName }}]"
                                                                value="{{ old($oldKey) }}"
                                                                @disabled($line->pending_for_view <= 0)
                                                            >
                                                        @elseif($fieldName === 'motor_number' && $tracking === 'serial')
                                                            <textarea
                                                                name="line_import_data[{{ $lineId }}][{{ $fieldName }}]"
                                                                rows="3"
                                                                placeholder="Un motor por línea, en el mismo orden que las series"
                                                                @disabled($line->pending_for_view <= 0)
                                                            >{{ old($oldKey) }}</textarea>
                                                        @else
                                                            <input
                                                                type="text"
                                                                name="line_import_data[{{ $lineId }}][{{ $fieldName }}]"
                                                                value="{{ old($oldKey) }}"
                                                                @disabled($line->pending_for_view <= 0)
                                                            >
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="help" style="margin-top:8px;">
                                                Los datos comunes se copiarán automáticamente si esta línea queda vacía.
                                            </div>
                                        </div>
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
