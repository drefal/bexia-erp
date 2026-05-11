<div class="po-lines-wrap">
    <style>
        .po-card {
            border: 1px solid #d9e2ef;
            border-radius: 14px;
            background: #ffffff;
            overflow: visible;
        }

        .po-card-header {
            padding: 14px 16px;
            border-bottom: 1px solid #e5edf6;
        }

        .po-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 13px;
        }

        .po-subtitle {
            color: #64748b;
            font-size: 11px;
            margin-top: 2px;
        }

        .po-add-grid {
            display: grid;
            grid-template-columns: minmax(260px, 2fr) minmax(190px, 1.25fr) minmax(150px, 1fr) minmax(100px, .7fr) minmax(140px, 1fr) minmax(150px, 1fr) 92px;
            gap: 10px;
            align-items: end;
            padding: 14px 16px;
            position: relative;
        }

        .po-edit-panel {
            border-top: 1px solid #e5edf6;
            padding: 14px 16px;
            background: #f8fafc;
        }

        .po-edit-grid {
            display: grid;
            grid-template-columns: minmax(260px, 2fr) minmax(190px, 1.25fr) minmax(150px, 1fr) minmax(100px, .7fr) minmax(140px, 1fr) minmax(150px, 1fr) 110px 95px;
            gap: 10px;
            align-items: end;
        }

        .po-field {
            position: relative;
            min-width: 0;
        }

        .po-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .po-input,
        .po-select {
            width: 100%;
            height: 37px;
            border: 1px solid #cfd8e6;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font-size: 13px;
            padding: 0 10px;
            outline: none;
        }

        .po-input[disabled] {
            background: #f8fafc;
            color: #475569;
        }

        .po-input:focus,
        .po-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .10);
        }

        .po-input-num {
            text-align: right;
        }

        .po-btn {
            height: 37px;
            border: 0;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            padding: 0 14px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(37, 99, 235, .18);
        }

        .po-btn:hover {
            background: #1d4ed8;
        }

        .po-btn-gray {
            background: #f1f5f9;
            color: #0f172a;
            box-shadow: none;
            border: 1px solid #cfd8e6;
        }

        .po-btn-gray:hover {
            background: #e2e8f0;
        }

        .po-results {
            position: absolute;
            z-index: 100;
            left: 0;
            right: 0;
            top: 62px;
            max-height: 240px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #cfd8e6;
            border-radius: 8px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .14);
        }

        .po-result-btn {
            width: 100%;
            display: block;
            padding: 9px 10px;
            text-align: left;
            font-size: 13px;
            color: #0f172a;
            background: #fff;
            border: 0;
            border-bottom: 1px solid #eef2f7;
            cursor: pointer;
        }

        .po-result-btn:hover {
            background: #eef5ff;
        }

        .po-products-head {
            border-top: 1px solid #e5edf6;
            padding: 12px 16px 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .po-search-lines {
            width: min(300px, 100%);
            height: 36px;
            border: 1px solid #cfd8e6;
            border-radius: 8px;
            padding: 0 10px;
            font-size: 13px;
        }

        .po-table-wrap {
            overflow-x: auto;
        }

        .po-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .po-table th {
            background: #f8fafc;
            color: #0f172a;
            text-align: left;
            font-weight: 700;
            padding: 10px 8px;
            border-top: 1px solid #e5edf6;
            border-bottom: 1px solid #d9e2ef;
            white-space: nowrap;
        }

        .po-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #eef2f7;
            color: #0f172a;
            vertical-align: middle;
        }

        .po-table .right {
            text-align: right;
        }

        .po-link {
            border: 0;
            background: transparent;
            color: #2563eb;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }

        .po-link:hover {
            text-decoration: underline;
        }

        .po-delete {
            color: #dc2626;
            margin-left: 10px;
        }

        .po-totals td {
            background: #f8fafc;
            border-bottom: 0;
            padding: 7px 8px;
        }

        .po-total-label {
            text-align: right;
            font-weight: 700;
            color: #334155;
        }

        .po-total-value {
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }

        .po-locked {
            padding: 14px 16px;
            color: #64748b;
            font-size: 13px;
            border-top: 1px solid #e5edf6;
        }

        .po-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 23, 42, .45);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .po-modal {
            width: min(460px, 100%);
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .25);
            padding: 24px;
        }

        .po-modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .po-modal-text {
            color: #475569;
            font-size: 13px;
            margin-bottom: 22px;
        }

        .po-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        @media (max-width: 1250px) {
            .po-add-grid,
            .po-edit-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 720px) {
            .po-add-grid,
            .po-edit-grid {
                grid-template-columns: 1fr;
            }

            .po-products-head {
                align-items: stretch;
                flex-direction: column;
            }

            .po-search-lines {
                width: 100%;
            }
        }
    </style>

    <div class="po-card">
        <div class="po-card-header">
            <div class="po-title">Agregar producto</div>
            <div class="po-subtitle">
                Busca el producto, elige variante si aplica, captura cantidad y costo.
            </div>
        </div>

        @if($isDraft)
            <div class="po-add-grid">
                <div class="po-field">
                    <label class="po-label">Producto</label>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="productSearch"
                        placeholder="Buscar producto..."
                        class="po-input"
                        @if($editingLineId) disabled @endif
                    >

                    @if(! $editingLineId && ! empty($productResults))
                        <div class="po-results">
                            @foreach($productResults as $product)
                                <button
                                    type="button"
                                    wire:click="selectProduct({{ $product['id'] }})"
                                    class="po-result-btn"
                                >
                                    {{ $product['label'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="po-field">
                    <label class="po-label">Variante</label>
                    <select wire:model.live="selectedVariantId" class="po-select" @if($editingLineId) disabled @endif>
                        <option value="">Sin variante</option>
                        @foreach($variantOptions as $variant)
                            <option value="{{ $variant['id'] }}">{{ $variant['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="po-field">
                    <label class="po-label">Unidad</label>
                    <select wire:model.live="selectedUnitKey" class="po-select" @if($editingLineId) disabled @endif>
                        @forelse($unitOptions as $key => $unit)
                            <option value="{{ $key }}">{{ $unit['label'] }}</option>
                        @empty
                            <option value="base">Pieza</option>
                        @endforelse
                    </select>
                </div>

                <div class="po-field">
                    <label class="po-label">Cantidad</label>
                    <input
                        type="number"
                        step="0.000001"
                        min="0"
                        wire:model.defer="newQuantity"
                        class="po-input po-input-num"
                        @if($editingLineId) disabled @endif
                    >
                </div>

                <div class="po-field">
                    <label class="po-label">Costo s/IVA</label>
                    <input
                        type="number"
                        step="0.0001"
                        min="0"
                        wire:model.defer="newUnitCostWithoutTax"
                        class="po-input po-input-num"
                        @if($editingLineId) disabled @endif
                    >
                </div>

                <div class="po-field">
                    <label class="po-label">Impuesto</label>
                    <select wire:model.defer="newTaxRate" class="po-select" @if($editingLineId) disabled @endif>
                        @foreach($taxOptions as $rate => $label)
                            <option value="{{ $rate }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="po-field">
                    <label class="po-label">&nbsp;</label>
                    <button
                        type="button"
                        wire:click="addProduct"
                        class="po-btn"
                        @if($editingLineId) disabled style="opacity:.55; cursor:not-allowed;" @endif
                    >
                        Agregar
                    </button>
                </div>
            </div>

            @if($editingLineId)
                <div class="po-edit-panel">
                    <div class="po-title" style="margin-bottom:10px;">Editar producto</div>

                    <div class="po-edit-grid">
                        <div class="po-field">
                            <label class="po-label">Producto</label>
                            <input type="text" value="{{ $editingProductLabel }}" class="po-input" disabled>
                        </div>

                        <div class="po-field">
                            <label class="po-label">Variante</label>
                            <input type="text" value="{{ $editingVariantLabel ?: '—' }}" class="po-input" disabled>
                        </div>

                        <div class="po-field">
                            <label class="po-label">Unidad</label>
                            <input type="text" value="{{ $editingUnitLabel ?: 'Pieza' }}" class="po-input" disabled>
                        </div>

                        <div class="po-field">
                            <label class="po-label">Cantidad</label>
                            <input type="number" step="0.000001" min="0" wire:model.defer="editQuantity" class="po-input po-input-num">
                        </div>

                        <div class="po-field">
                            <label class="po-label">Costo s/IVA</label>
                            <input type="number" step="0.0001" min="0" wire:model.defer="editUnitCostWithoutTax" class="po-input po-input-num">
                        </div>

                        <div class="po-field">
                            <label class="po-label">Impuesto</label>
                            <select wire:model.defer="editTaxRate" class="po-select">
                                @foreach($taxOptions as $rate => $label)
                                    <option value="{{ $rate }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="po-field">
                            <label class="po-label">&nbsp;</label>
                            <button type="button" wire:click="saveEditLine" class="po-btn">
                                Guardar
                            </button>
                        </div>

                        <div class="po-field">
                            <label class="po-label">&nbsp;</label>
                            <button type="button" wire:click="cancelEditLine" class="po-btn po-btn-gray">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <div class="po-field" style="margin-top:10px;">
                        <label class="po-label">Notas de línea</label>
                        <input type="text" wire:model.defer="editNotes" class="po-input" placeholder="Notas de línea">
                    </div>
                </div>
            @endif
        @else
            <div class="po-locked">
                Esta orden ya no está en borrador. No se pueden agregar productos.
            </div>
        @endif

        <div class="po-products-head">
            <div>
                <div class="po-title">Productos agregados</div>
                <div class="po-subtitle">Partidas de esta orden de compra.</div>
            </div>

            <input
                type="text"
                wire:model.live.debounce.300ms="lineSearch"
                placeholder="Buscar en productos agregados..."
                class="po-search-lines"
            >
        </div>

        @php
            $visibleLines = collect($lines)
                ->filter(function ($line) use ($lineSearch) {
                    $search = mb_strtolower(trim((string) $lineSearch));

                    if ($search === '') {
                        return true;
                    }

                    return str_contains(mb_strtolower($line['product_label'] ?? ''), $search)
                        || str_contains(mb_strtolower($line['variant_label'] ?? ''), $search)
                        || str_contains(mb_strtolower($line['purchase_unit_label'] ?? ''), $search);
                })
                ->values();
        @endphp

        <div class="po-table-wrap">
            <table class="po-table">
                <thead>
                    <tr>
                        <th style="min-width: 220px;">Producto</th>
                        <th style="min-width: 130px;">Variante</th>
                        <th style="min-width: 110px;">Unidad</th>
                        <th class="right" style="min-width: 95px;">Cantidad</th>
                        <th class="right" style="min-width: 95px;">Cant. base</th>
                        <th class="right" style="min-width: 110px;">Costo s/IVA</th>
                        <th class="right" style="min-width: 80px;">IVA</th>
                        <th class="right" style="min-width: 110px;">Costo c/IVA</th>
                        <th class="right" style="min-width: 110px;">Importe</th>
                        <th class="right" style="min-width: 105px;">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($visibleLines as $line)
                        <tr>
                            <td>{{ $line['product_label'] ?: '—' }}</td>
                            <td>{{ $line['variant_label'] ?: '—' }}</td>
                            <td>{{ $line['purchase_unit_label'] ?: '—' }}</td>
                            <td class="right">{{ number_format((float) $line['ordered_quantity'], 2) }}</td>
                            <td class="right">{{ number_format((float) $line['base_quantity'], 2) }}</td>
                            <td class="right">$ {{ number_format((float) $line['unit_cost_without_tax'], 4) }}</td>
                            <td class="right">{{ number_format((float) $line['tax_rate'], 2) }}%</td>
                            <td class="right">$ {{ number_format((float) ($line['unit_cost_with_tax'] ?? 0), 4) }}</td>
                            <td class="right"><strong>$ {{ number_format((float) $line['line_total_with_tax'], 2) }}</strong></td>
                            <td class="right">
                                @if($isDraft)
                                    <button type="button" wire:click="editLine({{ $line['id'] }})" class="po-link">
                                        Editar
                                    </button>

                                    <button type="button" wire:click="confirmDeleteLine({{ $line['id'] }})" class="po-link po-delete">
                                        Eliminar
                                    </button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align:center; color:#64748b; padding:20px;">
                                Sin productos agregados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot class="po-totals">
                    <tr>
                        <td colspan="9" class="po-total-label">Importe sin impuestos:</td>
                        <td class="po-total-value">$ {{ number_format((float) $subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="9" class="po-total-label">IVA:</td>
                        <td class="po-total-value">$ {{ number_format((float) $taxTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="9" class="po-total-label" style="font-size:13px;">Total:</td>
                        <td class="po-total-value" style="font-size:13px;">$ {{ number_format((float) $total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if($deleteLineId)
        <div class="po-modal-backdrop">
            <div class="po-modal">
                <div class="po-modal-title">Eliminar producto</div>

                <div class="po-modal-text">
                    ¿Eliminar <strong>{{ $deleteLineLabel }}</strong> de la orden?
                </div>

                <div class="po-modal-actions">
                    <button type="button" wire:click="cancelDeleteLine" class="po-btn po-btn-gray">
                        Cancelar
                    </button>

                    <button type="button" wire:click="deleteConfirmedLine" class="po-btn" style="background:#dc2626;">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
