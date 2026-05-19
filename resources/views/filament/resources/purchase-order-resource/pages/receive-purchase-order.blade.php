<x-filament-panels::page>
    @php
        $order = $this->getOrderRow();
        $lines = $this->getLinesForReceipt();
        $hasTracking = collect($lines)->contains(fn ($line) => in_array((string) ($line->tracking_for_view ?? 'none'), ['lot', 'serial'], true));
        $hasAdvancedTracking = collect($lines)->contains(fn ($line) => in_array((string) ($line->advanced_tracking_mode_for_view ?? 'none'), ['warning', 'required'], true));
        $showTrackingDetail = $hasTracking || $hasAdvancedTracking;
        $mainColspan = 8;

        $importFieldLabels = [
            'motor_number' => 'Motor',
            'customs_entry_number' => 'Pedimento',
            'customs_entry_date' => 'Fecha ped.',
            'customs_office' => 'Aduana',
            'imported_model' => 'Modelo',
            'imported_color' => 'Color',
            'import_document_reference' => 'Ref. doc.',
        ];

        $customsOfficeOptions = collect();

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('customs_offices')) {
                $customsOfficeOptions = \Illuminate\Support\Facades\DB::table('customs_offices')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['name', 'display_name', 'code']);
            }
        } catch (\Throwable $e) {
            $customsOfficeOptions = collect();
        }
    
        $existingSerialNumbersForClientValidation = collect();

        try {
            if (
                \Illuminate\Support\Facades\Schema::hasTable('stock_serial_numbers') &&
                \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'serial_number')
            ) {
                $existingSerialQuery = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
                    ->whereNotNull('serial_number')
                    ->where('serial_number', '<>', '');

                if (
                    \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'company_id') &&
                    ! empty($order->company_id)
                ) {
                    $existingSerialQuery->where('company_id', $order->company_id);
                }

                $existingSerialNumbersForClientValidation = $existingSerialQuery
                    ->pluck('serial_number')
                    ->map(fn ($serial) => mb_strtolower(trim((string) $serial)))
                    ->filter()
                    ->unique()
                    ->values();
            }
        } catch (\Throwable $e) {
            $existingSerialNumbersForClientValidation = collect();
        }

@endphp

    
<style>
    .bexia-serial-error-backdrop {
        position: fixed;
        inset: 0;
        z-index: 99995;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, 0.48);
    }

    .bexia-serial-error-backdrop.is-open {
        display: flex;
    }

    .bexia-serial-error-modal {
        width: min(720px, 100%);
        max-height: min(680px, calc(100vh - 48px));
        overflow: hidden;
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid #fecaca;
        box-shadow: 0 28px 80px rgba(15, 23, 42, 0.35);
    }

    .bexia-serial-error-header {
        padding: 20px 22px;
        border-bottom: 1px solid #fecaca;
        background: #fef2f2;
    }

    .bexia-serial-error-title {
        font-size: 18px;
        line-height: 1.3;
        font-weight: 900;
        color: #991b1b;
    }

    .bexia-serial-error-subtitle {
        margin-top: 5px;
        font-size: 13px;
        line-height: 1.45;
        color: #7f1d1d;
    }

    .bexia-serial-error-body {
        padding: 18px 22px;
        max-height: 380px;
        overflow: auto;
    }

    .bexia-serial-error-list {
        margin: 0;
        padding-left: 20px;
        color: #0f172a;
        font-size: 14px;
        line-height: 1.5;
    }

    .bexia-serial-error-list li {
        margin-bottom: 6px;
    }

    .bexia-serial-error-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px;
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .bexia-serial-error-btn {
        min-height: 40px;
        border-radius: 12px;
        padding: 0 16px;
        font-size: 14px;
        font-weight: 900;
        border: 1px solid #dc2626;
        background: #dc2626;
        color: #ffffff;
        cursor: pointer;
    }

    .bexia-serial-error-btn:hover {
        background: #b91c1c;
    }
</style>

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
            grid-template-columns: repeat(6, minmax(0, 1fr));
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

        .tracking-summary-row td {
            border-bottom: 0;
        }

        .tracking-detail-row td {
            padding: 0 10px 16px 10px;
            background: #fff;
        }

        .tracking-detail-panel {
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            background: #fbfdff;
            padding: 12px;
            width: 100%;
        }

        .tracking-title {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 10px;
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

        .lot-import-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .serial-table-wrap {
            overflow-x: auto;
            width: 100%;
        }

        .serial-import-table {
            width: 100%;
            min-width: 1120px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .serial-import-table th {
            background: #f8fafc;
            border-bottom: 1px solid #dbe3ef;
            padding: 6px;
            font-weight: 900;
            text-align: left;
            white-space: nowrap;
        }

        .serial-import-table td {
            border-bottom: 1px solid #edf2f7;
            padding: 6px;
            vertical-align: top;
        }

        .serial-import-table input {
            min-height: 32px;
            padding: 6px 8px;
            border-radius: 8px;
            font-size: 12px;
        }

        .serial-index {
            width: 38px;
            text-align: center;
            font-weight: 900;
            color: #475569;
        }

        .serial-vin-col {
            min-width: 190px;
        }

        .pedimento-input {
            font-variant-numeric: tabular-nums;
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

        @media (max-width: 1300px) {
            .import-grid,
            .lot-import-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .import-grid,
            .lot-import-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
        }
    </style>

    <datalist id="bexia-customs-offices">
        @foreach($customsOfficeOptions as $office)
            <option value="{{ $office->name }}">{{ $office->display_name ?: $office->name }}</option>
        @endforeach
    </datalist>

    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    <div class="bexia-receipt-card">
        <form method="POST" action="{{ $this->postActionUrl() }}" onsubmit="return window.bexiaConfirmWarningTrackingBeforeSubmit ? window.bexiaConfirmWarningTrackingBeforeSubmit(this) : true;">
            @csrf

            <div class="bexia-receipt-content">
                <div class="notice">
                    @if($hasTracking || $hasAdvancedTracking)
                        Las líneas con lote, número de serie o trazabilidad avanzada pedirán datos adicionales.
                        El pedimento se validará con formato <strong>## ## #### #######</strong>, por ejemplo <strong>26 16 1663 6000377</strong>.
                    @else
                        Esta orden no contiene productos con lote, número de serie ni trazabilidad avanzada. La recepción se capturará solo por cantidad.
                    @endif
                </div>

                @if($hasAdvancedTracking)
                    <div class="import-common">
                        <div class="import-common-title">Datos comunes de importación</div>
                        <div class="help">
                            Si todos los productos de esta recepción comparten pedimento, fecha, aduana, modelo o color, captura los datos aquí y se copiarán a las líneas o series que no tengan valor propio.
                        </div>

                        <div class="import-grid">
                            <div class="import-field">
                                <label>Número de pedimento</label>
                                <input
                                    class="pedimento-input"
                                    type="text"
                                    name="common_import_data[customs_entry_number]"
                                    value="{{ old('common_import_data.customs_entry_number') }}"
                                    placeholder="Ej. 26 16 1663 6000377"
                                    pattern="\d{2}\s\d{2}\s\d{4}\s\d{7}"
                                    title="Formato: ## ## #### #######. Ejemplo: 26 16 1663 6000377"
                                    onblur="window.bexiaFormatPedimento && window.bexiaFormatPedimento(this)"
                                >
                            </div>

                            <div class="import-field">
                                <label>Fecha de pedimento</label>
                                <input type="date" name="common_import_data[customs_entry_date]" value="{{ old('common_import_data.customs_entry_date') }}">
                            </div>

                            <div class="import-field">
                                <label>Aduana</label>
                                <input type="text" list="bexia-customs-offices" name="common_import_data[customs_office]" value="{{ old('common_import_data.customs_office') }}" placeholder="Ej. MANZANILLO">
                            </div>

                            <div class="import-field">
                                <label>Modelo importado</label>
                                <input type="text" name="common_import_data[imported_model]" value="{{ old('common_import_data.imported_model') }}" placeholder="Ej. GT60">
                            </div>

                            <div class="import-field">
                                <label>Color importado</label>
                                <input type="text" name="common_import_data[imported_color]" value="{{ old('common_import_data.imported_color') }}" placeholder="Ej. GRAY">
                            </div>

                            <div class="import-field">
                                <label>Referencia documento/factura</label>
                                <input type="text" name="common_import_data[import_document_reference]" value="{{ old('common_import_data.import_document_reference') }}" placeholder="Factura, XML o referencia">
                            </div>
                        </div>

                        <label class="apply-row">
                            <input type="checkbox" name="apply_common_import_to_all" value="1" @checked(old('apply_common_import_to_all', '1') === '1')>
                            Aplicar estos datos a todas las líneas o series que no tengan datos propios.
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
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($lines as $line)
                        @php
                            $tracking = (string) ($line->tracking_for_view ?? 'none');
                            $lineId = (int) $line->id;
                            $advancedMode = (string) ($line->advanced_tracking_mode_for_view ?? 'none');
                            $advancedFields = array_values((array) ($line->advanced_tracking_fields_for_view ?? []));
                            $hasAdvancedLine = in_array($advancedMode, ['warning', 'required'], true);
                            $serialRowsOld = old('serial_import_rows.' . $lineId, []);
                            $serialRowCount = max(1, (int) ceil((float) $line->pending_for_view));
                            if (is_array($serialRowsOld) && count($serialRowsOld) > 0) {
                                $serialRowCount = max($serialRowCount, count($serialRowsOld));
                            }
                            $serialFieldColumns = array_values(array_filter($advancedFields, fn ($field) => $field !== 'serial_number'));
                            $needsDetail = $tracking !== 'none' || $hasAdvancedLine;
                        @endphp

                        <tr class="tracking-summary-row">
                            <td><strong>{{ $line->product_label ?? 'Producto' }}</strong></td>
                            <td>{{ $line->variant_label ?? '—' }}</td>
                            <td>{{ $line->purchase_unit_label ?? 'Unidad' }}</td>
                            <td class="qty">{{ number_format((float) $line->ordered_for_view, 6) }}</td>
                            <td class="qty">{{ number_format((float) $line->received_for_view, 6) }}</td>
                            <td class="qty">{{ number_format((float) $line->pending_for_view, 6) }}</td>
                            <td class="qty">
                                <input
                                    class="receive-input"
                                    data-quantity-line="{{ $lineId }}"
                                    type="number"
                                    step="0.000001"
                                    min="0"
                                    max="{{ $line->pending_for_view }}"
                                    name="quantities[{{ $lineId }}]"
                                    value="{{ old('quantities.' . $lineId, $line->pending_for_view > 0 ? number_format((float) $line->pending_for_view, 6, '.', '') : '0') }}"
                                    oninput="window.bexiaSyncSerialRows && window.bexiaSyncSerialRows({{ $lineId }})"
                                    @disabled($line->pending_for_view <= 0)
                                >
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-gray btn-small"
                                    onclick="this.closest('tr').querySelector('.receive-input').value='0'; window.bexiaSyncSerialRows && window.bexiaSyncSerialRows({{ $lineId }});"
                                >
                                    No recibir
                                </button>
                            </td>
                        </tr>

                        @if($needsDetail)
                            <tr class="tracking-detail-row">
                                <td colspan="{{ $mainColspan }}">
                                    <div class="tracking-detail-panel" data-bexia-tracking-mode="{{ $advancedMode }}" data-bexia-product-name="{{ e($line->product_label ?? 'Producto') }}" data-bexia-advanced-fields='@json($advancedFields)'>
                                        <div class="tracking-title">
                                            <div>
                                                @if($tracking === 'lot')
                                                    <span class="tracking-label lot">Lote</span>
                                                @elseif($tracking === 'serial')
                                                    <span class="tracking-label serial">Número de serie</span>
                                                @else
                                                    <span class="tracking-label">Trazabilidad</span>
                                                @endif

                                                @if($hasAdvancedLine)
                                                    <span class="advanced-badge {{ $advancedMode === 'required' ? 'required' : '' }}">
                                                        {{ $advancedMode === 'required' ? 'Obligatorio' : 'Aviso' }}
                                                    </span>
                                                @endif
                                            </div>

                                            <span class="help">
                                                @if($tracking === 'serial')
                                                    Una fila por unidad recibida.
                                                @elseif($tracking === 'lot')
                                                    Captura lote y datos de importación de la línea.
                                                @else
                                                    Datos adicionales de importación.
                                                @endif
                                            </span>
                                        </div>

                                        @if($tracking === 'lot')
                                            <div class="lot-import-grid">
                                                <div class="line-import-field">
                                                    <label>Número de lote</label>
                                                    <input type="text" name="lot_numbers[{{ $lineId }}]" value="{{ old('lot_numbers.' . $lineId) }}" placeholder="Número de lote" @disabled($line->pending_for_view <= 0)>
                                                </div>

                                                <div class="line-import-field">
                                                    <label>Caducidad</label>
                                                    <input type="date" name="lot_expiration_dates[{{ $lineId }}]" value="{{ old('lot_expiration_dates.' . $lineId) }}" @disabled($line->pending_for_view <= 0)>
                                                </div>

                                                @if($hasAdvancedLine)
                                                    @foreach($advancedFields as $fieldName)
                                                        @continue($fieldName === 'serial_number')
                                                        @php
                                                            $label = $importFieldLabels[$fieldName] ?? $fieldName;
                                                            $oldKey = 'line_import_data.' . $lineId . '.' . $fieldName;
                                                        @endphp

                                                        <div class="line-import-field">
                                                            <label>{{ $label }}</label>

                                                            @if($fieldName === 'customs_entry_date')
                                                                <input type="date" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                            @elseif($fieldName === 'customs_office')
                                                                <input type="text" list="bexia-customs-offices" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                            @elseif($fieldName === 'customs_entry_number')
                                                                <input class="pedimento-input" type="text" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" placeholder="## ## #### #######" pattern="\d{2}\s\d{2}\s\d{4}\s\d{7}" title="Formato: ## ## #### #######" onblur="window.bexiaFormatPedimento && window.bexiaFormatPedimento(this)" @disabled($line->pending_for_view <= 0)>
                                                            @else
                                                                <input type="text" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>

                                            <div class="help" style="margin-top:8px;">
                                                Los datos comunes se copiarán automáticamente si esta línea queda vacía.
                                            </div>
                                        @elseif($tracking === 'serial')
                                            @if($hasAdvancedLine)
                                                <div class="serial-table-wrap">
                                                    <table class="serial-import-table">
                                                        <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th class="serial-vin-col">VIN / número de serie</th>
                                                            @foreach($serialFieldColumns as $fieldName)
                                                                <th>{{ $importFieldLabels[$fieldName] ?? $fieldName }}</th>
                                                            @endforeach
                                                        </tr>
                                                        </thead>
                                                        <tbody data-serial-table="{{ $lineId }}">
                                                        @for($i = 0; $i < $serialRowCount; $i++)
                                                            <tr data-serial-row>
                                                                <td class="serial-index">{{ $i + 1 }}</td>
                                                                <td>
                                                                    <input type="text" name="serial_import_rows[{{ $lineId }}][{{ $i }}][serial_number]" value="{{ old('serial_import_rows.' . $lineId . '.' . $i . '.serial_number') }}" placeholder="Serie / VIN" @disabled($line->pending_for_view <= 0)>
                                                                </td>

                                                                @foreach($serialFieldColumns as $fieldName)
                                                                    @php
                                                                        $oldKey = 'serial_import_rows.' . $lineId . '.' . $i . '.' . $fieldName;
                                                                    @endphp
                                                                    <td>
                                                                        @if($fieldName === 'customs_entry_date')
                                                                            <input type="date" name="serial_import_rows[{{ $lineId }}][{{ $i }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                                        @elseif($fieldName === 'customs_office')
                                                                            <input type="text" list="bexia-customs-offices" name="serial_import_rows[{{ $lineId }}][{{ $i }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                                        @elseif($fieldName === 'customs_entry_number')
                                                                            <input class="pedimento-input" type="text" name="serial_import_rows[{{ $lineId }}][{{ $i }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" placeholder="## ## #### #######" pattern="\d{2}\s\d{2}\s\d{4}\s\d{7}" title="Formato: ## ## #### #######" onblur="window.bexiaFormatPedimento && window.bexiaFormatPedimento(this)" @disabled($line->pending_for_view <= 0)>
                                                                        @else
                                                                            <input type="text" name="serial_import_rows[{{ $lineId }}][{{ $i }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                                        @endif
                                                                    </td>
                                                                @endforeach
                                                            </tr>
                                                        @endfor
                                                        </tbody>
                                                    </table>

                                                    <template data-serial-template="{{ $lineId }}">
                                                        <tr data-serial-row>
                                                            <td class="serial-index">__NUMBER__</td>
                                                            <td><input type="text" name="serial_import_rows[{{ $lineId }}][__INDEX__][serial_number]" placeholder="Serie / VIN"></td>

                                                            @foreach($serialFieldColumns as $fieldName)
                                                                <td>
                                                                    @if($fieldName === 'customs_entry_date')
                                                                        <input type="date" name="serial_import_rows[{{ $lineId }}][__INDEX__][{{ $fieldName }}]">
                                                                    @elseif($fieldName === 'customs_office')
                                                                        <input type="text" list="bexia-customs-offices" name="serial_import_rows[{{ $lineId }}][__INDEX__][{{ $fieldName }}]">
                                                                    @elseif($fieldName === 'customs_entry_number')
                                                                        <input class="pedimento-input" type="text" name="serial_import_rows[{{ $lineId }}][__INDEX__][{{ $fieldName }}]" placeholder="## ## #### #######" pattern="\d{2}\s\d{2}\s\d{4}\s\d{7}" title="Formato: ## ## #### #######" onblur="window.bexiaFormatPedimento && window.bexiaFormatPedimento(this)">
                                                                    @else
                                                                        <input type="text" name="serial_import_rows[{{ $lineId }}][__INDEX__][{{ $fieldName }}]">
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    </template>

                                                    <div class="help" style="margin-top:8px;">
                                                        Los datos comunes se copiarán automáticamente a cada serie si la celda queda vacía.
                                                    </div>
                                                </div>
                                            @else
                                                <textarea name="serial_numbers[{{ $lineId }}]" rows="4" placeholder="Un número de serie por línea" @disabled($line->pending_for_view <= 0)>{{ old('serial_numbers.' . $lineId) }}</textarea>
                                                <div class="help">Captura un número de serie por unidad recibida. También puedes separar por coma.</div>
                                            @endif
                                        @elseif($hasAdvancedLine)
                                            <div class="lot-import-grid">
                                                @foreach($advancedFields as $fieldName)
                                                    @continue($fieldName === 'serial_number')
                                                    @php
                                                        $label = $importFieldLabels[$fieldName] ?? $fieldName;
                                                        $oldKey = 'line_import_data.' . $lineId . '.' . $fieldName;
                                                    @endphp

                                                    <div class="line-import-field">
                                                        <label>{{ $label }}</label>

                                                        @if($fieldName === 'customs_entry_date')
                                                            <input type="date" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                        @elseif($fieldName === 'customs_office')
                                                            <input type="text" list="bexia-customs-offices" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                        @elseif($fieldName === 'customs_entry_number')
                                                            <input class="pedimento-input" type="text" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" placeholder="## ## #### #######" pattern="\d{2}\s\d{2}\s\d{4}\s\d{7}" title="Formato: ## ## #### #######" onblur="window.bexiaFormatPedimento && window.bexiaFormatPedimento(this)" @disabled($line->pending_for_view <= 0)>
                                                        @else
                                                            <input type="text" name="line_import_data[{{ $lineId }}][{{ $fieldName }}]" value="{{ old($oldKey) }}" @disabled($line->pending_for_view <= 0)>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

                <div style="margin-top:18px;">
                    <label style="font-weight:800; display:block; margin-bottom:6px;">Notas de recepción</label>
                    <textarea name="notes" rows="3" placeholder="Notas opcionales de recepción">{{ old('notes') }}</textarea>
                </div>

                <div class="actions">
                    <a class="btn btn-gray" href="{{ $this->cancelUrl() }}">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar recepción</button>
                </div>
            </div>
        </form>
    </div>

    
<style>
    .bexia-warning-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 99990;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, 0.45);
    }

    .bexia-warning-modal-backdrop.is-open {
        display: flex;
    }

    .bexia-warning-modal {
        width: min(760px, 100%);
        max-height: min(720px, calc(100vh - 48px));
        overflow: hidden;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 28px 80px rgba(15, 23, 42, 0.35);
        border: 1px solid #fed7aa;
    }

    .bexia-warning-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-bottom: 1px solid #fed7aa;
        background: #fff7ed;
    }

    .bexia-warning-modal-title {
        font-size: 18px;
        line-height: 1.3;
        font-weight: 900;
        color: #9a3412;
    }

    .bexia-warning-modal-subtitle {
        margin-top: 5px;
        font-size: 13px;
        line-height: 1.45;
        color: #7c2d12;
    }

    .bexia-warning-modal-close {
        border: 0;
        background: transparent;
        color: #9a3412;
        font-size: 22px;
        font-weight: 900;
        line-height: 1;
        cursor: pointer;
    }

    .bexia-warning-modal-body {
        padding: 18px 22px;
        max-height: 420px;
        overflow: auto;
    }

    .bexia-warning-modal-list {
        margin: 0;
        padding-left: 20px;
        color: #0f172a;
        font-size: 14px;
        line-height: 1.5;
    }

    .bexia-warning-modal-list li {
        margin-bottom: 6px;
    }

    .bexia-warning-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px;
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .bexia-warning-modal-btn {
        min-height: 40px;
        border-radius: 12px;
        padding: 0 16px;
        font-size: 14px;
        font-weight: 900;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
        cursor: pointer;
    }

    .bexia-warning-modal-btn:hover {
        background: #f1f5f9;
    }

    .bexia-warning-modal-btn-primary {
        border-color: #2563eb;
        background: #2563eb;
        color: #ffffff;
    }

    .bexia-warning-modal-btn-primary:hover {
        background: #1d4ed8;
    }
</style>

<script id="bexia-existing-serials-json" type="application/json">@json($existingSerialNumbersForClientValidation ?? [])</script>

<script>
        window.bexiaFormatPedimento = function (input) {
            const raw = String(input.value || '').trim();

            if (!raw) {
                return;
            }

            const digits = raw.replace(/\D+/g, '');

            if (digits.length === 15) {
                input.value = digits.slice(0, 2) + ' ' + digits.slice(2, 4) + ' ' + digits.slice(4, 8) + ' ' + digits.slice(8, 15);
                return;
            }

            input.value = raw.replace(/\s+/g, ' ');
        };



        window.bexiaWarningTrackingPendingForm = null;

        window.bexiaOpenWarningTrackingModal = function (missingItems, form) {
            const modal = document.getElementById('bexia-warning-tracking-modal');
            const list = document.getElementById('bexia-warning-tracking-list');

            if (!modal || !list) {
                return false;
            }

            window.bexiaWarningTrackingPendingForm = form;

            list.innerHTML = '';

            missingItems.slice(0, 60).forEach(function (item) {
                const li = document.createElement('li');
                li.textContent = item;
                list.appendChild(li);
            });

            if (missingItems.length > 60) {
                const li = document.createElement('li');
                li.textContent = '... y ' + (missingItems.length - 60) + ' campos adicionales.';
                list.appendChild(li);
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');

            return false;
        };

        window.bexiaCloseWarningTrackingModal = function () {
            const modal = document.getElementById('bexia-warning-tracking-modal');

            if (!modal) {
                return;
            }

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            window.bexiaWarningTrackingPendingForm = null;
        };

        
        window.bexiaNormalizeSerialValue = function (value) {
            return String(value || '')
                .trim()
                .replace(/\s+/g, ' ')
                .toLowerCase();
        };

        window.bexiaExistingSerialSet = function () {
            const holder = document.getElementById('bexia-existing-serials-json');

            if (!holder) {
                return new Set();
            }

            try {
                const values = JSON.parse(holder.textContent || '[]');

                return new Set(
                    Array.isArray(values)
                        ? values.map(window.bexiaNormalizeSerialValue).filter(Boolean)
                        : []
                );
            } catch (e) {
                return new Set();
            }
        };

        window.bexiaSerialFieldFromName = function (name) {
            if (!name) {
                return null;
            }

            if (name.includes('serial_numbers[')) {
                return 'serial_number';
            }

            const matches = Array.from(name.matchAll(/\[([a-zA-Z0-9_]+)\]/g));

            if (!matches.length) {
                return null;
            }

            return matches[matches.length - 1][1];
        };

        window.bexiaFindSerialDuplicateProblems = function (form) {
            const existingSerials = window.bexiaExistingSerialSet();
            const seenInForm = new Map();
            const problems = [];

            const serialInputs = Array.from(form.querySelectorAll('input[name], textarea[name]'))
                .filter(function (input) {
                    const name = input.getAttribute('name') || '';
                    const field = window.bexiaSerialFieldFromName(name);

                    return field === 'serial_number'
                        && !input.disabled
                        && input.type !== 'hidden';
                });

            serialInputs.forEach(function (input) {
                const serialOriginal = String(input.value || '').trim();
                const serialKey = window.bexiaNormalizeSerialValue(serialOriginal);

                if (!serialKey) {
                    return;
                }

                const row = input.closest('tr');
                const rowNumber = row && row.querySelector('.serial-index')
                    ? row.querySelector('.serial-index').textContent.trim()
                    : '';

                const panel = input.closest('.tracking-detail-panel');
                const productName = panel && panel.dataset.bexiaProductName
                    ? panel.dataset.bexiaProductName
                    : 'Producto';

                const label = rowNumber
                    ? productName + ' / fila ' + rowNumber + ': ' + serialOriginal
                    : productName + ': ' + serialOriginal;

                if (seenInForm.has(serialKey)) {
                    problems.push(label + ' está repetido en esta recepción.');
                } else {
                    seenInForm.set(serialKey, label);
                }

                if (existingSerials.has(serialKey)) {
                    problems.push(label + ' ya existe en inventario.');
                }
            });

            return Array.from(new Set(problems));
        };

        window.bexiaOpenSerialDuplicateModal = function (items) {
            const modal = document.getElementById('bexia-serial-error-modal');
            const list = document.getElementById('bexia-serial-error-list');

            if (!modal || !list) {
                alert('Hay números de serie duplicados. Corrige antes de continuar.');
                return false;
            }

            list.innerHTML = '';

            items.forEach(function (item) {
                const li = document.createElement('li');
                li.textContent = item;
                list.appendChild(li);
            });

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');

            return false;
        };

        window.bexiaCloseSerialDuplicateModal = function () {
            const modal = document.getElementById('bexia-serial-error-modal');

            if (!modal) {
                return;
            }

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        };


        window.bexiaConfirmWarningTrackingBeforeSubmit = function (form) {
            const serialDuplicateProblems = window.bexiaFindSerialDuplicateProblems
                ? window.bexiaFindSerialDuplicateProblems(form)
                : [];

            if (serialDuplicateProblems.length) {
                form.dataset.bexiaWarningTrackingApproved = '0';
                return window.bexiaOpenSerialDuplicateModal(serialDuplicateProblems);
            }


            if (form.dataset.bexiaWarningTrackingApproved === '1') {
                form.dataset.bexiaWarningTrackingApproved = '0';
                return true;
            }

            const warningModes = [
                'warning',
                'warn',
                'aviso',
                'recommended',
                'recommendation',
                'recomendada',
                'recommended_warning'
            ];

            const panels = Array.from(form.querySelectorAll('.tracking-detail-panel'))
                .filter(function (panel) {
                    const mode = String(panel.dataset.bexiaTrackingMode || '').trim().toLowerCase();

                    return warningModes.includes(mode);
                });

            if (!panels.length) {
                return true;
            }

            const labels = {
                serial_number: 'VIN / número de serie',
                lot_number: 'Número de lote',
                motor_number: 'Número de motor',
                customs_entry_number: 'Número de pedimento',
                customs_entry_date: 'Fecha de pedimento',
                customs_office: 'Aduana',
                imported_model: 'Modelo importado',
                imported_color: 'Color importado',
                import_document_reference: 'Referencia documento/factura'
            };

            const commonValues = {};
            const applyCommon = !!form.querySelector('input[name="apply_common_import_to_all"]:checked');

            form.querySelectorAll('input[name^="common_import_data["], textarea[name^="common_import_data["], select[name^="common_import_data["]').forEach(function (input) {
                const match = input.name.match(/^common_import_data\[([a-zA-Z0-9_]+)\]$/);

                if (match) {
                    commonValues[match[1]] = String(input.value || '').trim();
                }
            });

            const fieldFromName = function (name) {
                if (!name) {
                    return null;
                }

                if (name.includes('lot_numbers[')) {
                    return 'lot_number';
                }

                if (name.includes('serial_numbers[')) {
                    return 'serial_number';
                }

                const matches = Array.from(name.matchAll(/\[([a-zA-Z0-9_]+)\]/g));

                if (!matches.length) {
                    return null;
                }

                return matches[matches.length - 1][1];
            };

            const parseFields = function (panel) {
                let fields = [];

                try {
                    fields = JSON.parse(panel.dataset.bexiaAdvancedFields || '[]');
                } catch (e) {
                    fields = [];
                }

                if (!Array.isArray(fields)) {
                    fields = [];
                }

                fields = fields
                    .map(function (field) { return String(field || '').trim(); })
                    .filter(Boolean);

                if (!fields.length) {
                    panel.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (input) {
                        const field = fieldFromName(input.name);

                        if (field && !fields.includes(field)) {
                            fields.push(field);
                        }
                    });
                }

                return fields;
            };

            const isRelevantInput = function (input) {
                const name = input.getAttribute('name') || '';

                return name.includes('serial_import_rows')
                    || name.includes('line_import_data')
                    || name.includes('lot_numbers')
                    || name.includes('serial_numbers');
            };

            const missing = [];

            panels.forEach(function (panel, panelIndex) {
                const fieldsToCheck = parseFields(panel);
                const productName = panel.dataset.bexiaProductName
                    || (panel.closest('tr') && panel.closest('tr').previousElementSibling
                        ? (panel.closest('tr').previousElementSibling.querySelector('td strong')?.textContent || '').trim()
                        : '')
                    || 'Producto ' + (panelIndex + 1);

                panel.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (input) {
                    if (input.disabled || input.type === 'hidden') {
                        return;
                    }

                    if (!isRelevantInput(input)) {
                        return;
                    }

                    const field = fieldFromName(input.name);

                    if (!field) {
                        return;
                    }

                    if (fieldsToCheck.length && !fieldsToCheck.includes(field)) {
                        return;
                    }

                    const value = String(input.value || '').trim();

                    if (value !== '') {
                        return;
                    }

                    if (
                        applyCommon
                        && field !== 'serial_number'
                        && field !== 'lot_number'
                        && commonValues[field]
                    ) {
                        return;
                    }

                    const row = input.closest('tr');
                    const rowNumber = row && row.querySelector('.serial-index')
                        ? row.querySelector('.serial-index').textContent.trim()
                        : '';

                    const label = labels[field] || field;

                    missing.push(rowNumber
                        ? productName + ' / fila ' + rowNumber + ': ' + label
                        : productName + ': ' + label
                    );
                });
            });

            const uniqueMissing = Array.from(new Set(missing));

            if (!uniqueMissing.length) {
                return true;
            }

            return window.bexiaOpenWarningTrackingModal(uniqueMissing, form);
        };

        document.addEventListener('DOMContentLoaded', function () {

            document.querySelectorAll('[data-bexia-serial-error-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    window.bexiaCloseSerialDuplicateModal();
                });
            });

            const serialErrorModal = document.getElementById('bexia-serial-error-modal');

            if (serialErrorModal) {
                serialErrorModal.addEventListener('click', function (event) {
                    if (event.target === serialErrorModal) {
                        window.bexiaCloseSerialDuplicateModal();
                    }
                });
            }


            document.querySelectorAll('[data-bexia-warning-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    window.bexiaCloseWarningTrackingModal();
                });
            });

            const continueButton = document.getElementById('bexia-warning-tracking-continue');

            if (continueButton) {
                continueButton.addEventListener('click', function () {
                    const form = window.bexiaWarningTrackingPendingForm;

                    if (!form) {
                        window.bexiaCloseWarningTrackingModal();
                        return;
                    }

                    form.dataset.bexiaWarningTrackingApproved = '1';
                    window.bexiaCloseWarningTrackingModal();

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            }

            const modal = document.getElementById('bexia-warning-tracking-modal');

            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        window.bexiaCloseWarningTrackingModal();
                    }
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    window.bexiaCloseWarningTrackingModal();
                }
            });
        });


        window.bexiaSyncSerialRows = function (lineId) {
            const quantityInput = document.querySelector('[data-quantity-line="' + lineId + '"]');
            const tbody = document.querySelector('[data-serial-table="' + lineId + '"]');
            const template = document.querySelector('[data-serial-template="' + lineId + '"]');

            if (!quantityInput || !tbody || !template) {
                return;
            }

            const desired = Math.max(0, Math.ceil(parseFloat(quantityInput.value || '0')));
            const currentRows = Array.from(tbody.querySelectorAll('[data-serial-row]'));

            if (desired > currentRows.length) {
                for (let i = currentRows.length; i < desired; i++) {
                    const html = template.innerHTML
                        .replaceAll('__INDEX__', String(i))
                        .replaceAll('__NUMBER__', String(i + 1));

                    tbody.insertAdjacentHTML('beforeend', html);
                }
            }

            if (desired < currentRows.length) {
                for (let i = currentRows.length - 1; i >= desired; i--) {
                    currentRows[i].remove();
                }
            }

            Array.from(tbody.querySelectorAll('[data-serial-row]')).forEach(function (row, index) {
                const indexCell = row.querySelector('.serial-index');

                if (indexCell) {
                    indexCell.textContent = String(index + 1);
                }

                row.querySelectorAll('input').forEach(function (input) {
                    input.name = input.name.replace(/\[\d+\]/, '[' + index + ']');
                });
            });
        };
    </script>

<div id="bexia-warning-tracking-modal" class="bexia-warning-modal-backdrop" aria-hidden="true">
    <div class="bexia-warning-modal" role="dialog" aria-modal="true" aria-labelledby="bexia-warning-tracking-title">
        <div class="bexia-warning-modal-header">
            <div>
                <div id="bexia-warning-tracking-title" class="bexia-warning-modal-title">
                    Faltan datos recomendados de trazabilidad/importación
                </div>
                <div class="bexia-warning-modal-subtitle">
                    El producto está configurado como aviso. Puedes cancelar para completar los datos o guardar de todos modos.
                </div>
            </div>

            <button type="button" class="bexia-warning-modal-close" data-bexia-warning-close aria-label="Cerrar">×</button>
        </div>

        <div class="bexia-warning-modal-body">
            <ol id="bexia-warning-tracking-list" class="bexia-warning-modal-list"></ol>
        </div>

        <div class="bexia-warning-modal-footer">
            <button type="button" class="bexia-warning-modal-btn" data-bexia-warning-close>
                Cancelar y completar
            </button>

            <button type="button" class="bexia-warning-modal-btn bexia-warning-modal-btn-primary" id="bexia-warning-tracking-continue">
                Guardar de todos modos
            </button>
        </div>
    </div>
</div>


<div id="bexia-serial-error-modal" class="bexia-serial-error-backdrop" aria-hidden="true">
    <div class="bexia-serial-error-modal" role="dialog" aria-modal="true" aria-labelledby="bexia-serial-error-title">
        <div class="bexia-serial-error-header">
            <div id="bexia-serial-error-title" class="bexia-serial-error-title">
                No se puede guardar: número de serie duplicado
            </div>
            <div class="bexia-serial-error-subtitle">
                Corrige los números de serie indicados antes de continuar. Esta validación se revisa antes de los avisos de trazabilidad.
            </div>
        </div>

        <div class="bexia-serial-error-body">
            <ol id="bexia-serial-error-list" class="bexia-serial-error-list"></ol>
        </div>

        <div class="bexia-serial-error-footer">
            <button type="button" class="bexia-serial-error-btn" data-bexia-serial-error-close>
                Entendido
            </button>
        </div>
    </div>
</div>

</x-filament-panels::page>
