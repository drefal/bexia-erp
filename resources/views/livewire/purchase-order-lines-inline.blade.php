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
            grid-template-columns: minmax(250px, 2fr) minmax(180px, 1.2fr) minmax(140px, .9fr) minmax(85px, .55fr) minmax(95px, .6fr) minmax(135px, .85fr) minmax(145px, .9fr) 92px;
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
            grid-template-columns: minmax(250px, 2fr) minmax(180px, 1.2fr) minmax(140px, .9fr) minmax(85px, .55fr) minmax(95px, .6fr) minmax(135px, .85fr) minmax(145px, .9fr) 110px 95px;
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

        .po-hint {
            margin-top: 4px;
            font-size: 10px;
            line-height: 1.25;
            color: #64748b;
        }

        .po-suggest-btn {
            border: 0;
            background: transparent;
            color: #2563eb;
            font-size: 10px;
            font-weight: 700;
            padding: 0 0 0 4px;
            cursor: pointer;
        }

        .po-suggest-btn:hover {
            text-decoration: underline;
        }

        .po-cost-preview {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 8px;
            padding: 0 16px 14px 16px;
            color: #334155;
        }

        .po-preview-item {
            display: flex;
            align-items: center;
            gap: 5px;
            min-height: 30px;
            padding: 6px 9px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #ffffff;
            font-size: 11px;
            line-height: 1.2;
        }

        .po-preview-item strong {
            color: #0f172a;
            font-size: 11px;
            font-weight: 600;
        }

        .po-preview-unit {
            background: #eff6ff;
            border-color: #dbeafe;
        }

        .po-preview-total {
            background: #f0fdf4;
            border-color: #dcfce7;
        }

        .po-preview-label {
            color: #64748b;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .02em;
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

        .po-cell-main {
            font-size: 11px;
            font-weight: 600;
            line-height: 1.25;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .po-cell-sub {
            margin-top: 2px;
            color: #64748b;
            font-size: 9.5px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .po-th-title {
            font-size: 11px;
            font-weight: 700;
            line-height: 1.15;
            white-space: nowrap;
        }

        .po-th-sub {
            margin-top: 3px;
            color: #64748b;
            font-size: 9px;
            font-weight: 500;
            line-height: 1.1;
            white-space: nowrap;
        }

        .po-th-purchase {
            background: #f8fafc;
            border-left: 2px solid #cbd5e1;
        }

        .po-th-unit {
            background: #eff6ff;
            border-left: 2px solid #93c5fd;
        }

        .po-th-total {
            background: #f0fdf4;
            border-left: 2px solid #86efac;
        }

        .po-money-block {
            border-left: 2px solid transparent;
        }

        .po-money-purchase {
            background: #f8fafc;
            border-left-color: #cbd5e1;
        }

        .po-money-unit {
            background: #eff6ff;
            border-left-color: #93c5fd;
        }

        .po-money-total {
            background: #f0fdf4;
            border-left-color: #86efac;
        }

        .po-money-line {
            font-size: 11px;
            font-weight: 600;
            line-height: 1.25;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .po-money-line + .po-money-line {
            margin-top: 4px;
        }

        .po-money-label {
            margin-left: 3px;
            color: #64748b;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .po-money-context {
            margin-top: 3px;
            color: #64748b;
            font-size: 9px;
            line-height: 1.15;
            white-space: nowrap;
        }

        .po-money-unit .po-money-line:last-child {
            color: #1d4ed8;
        }

        .po-money-total .po-money-line:last-child {
            color: #166534;
            font-weight: 700;
        }

        .po-uxe-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            padding: 3px 7px;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            background: #eff6ff;
            color: #1e40af;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            font-variant-numeric: tabular-nums;
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
                Busca el producto, elige variante si aplica, captura UXE, cantidad, IVA y costo de compra.
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
                    <label class="po-label">UXE</label>
                    <input
                        type="number"
                        step="0.000001"
                        min="1"
                        wire:model.live.debounce.300ms="newPurchaseUnitFactor"
                        class="po-input po-input-num"
                        title="Unidades base por unidad comprada"
                        @if($editingLineId) disabled @endif
                    >

                    @if($newSuggestedUxeText !== '')
                        <div class="po-hint">
                            {{ $newSuggestedUxeText }}

                            @if($newSuggestedUxe)
                                <button
                                    type="button"
                                    wire:click="applySuggestedUxe"
                                    class="po-suggest-btn"
                                    @if($editingLineId) disabled @endif
                                >
                                    Usar
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="po-field">
                    <label class="po-label">Cantidad</label>
                    <input
                        type="number"
                        step="0.000001"
                        min="0"
                        wire:model.live.debounce.300ms="newQuantity"
                        class="po-input po-input-num"
                        @if($editingLineId) disabled @endif
                    >
                </div>

                <div class="po-field">
                    <label class="po-label">IVA</label>
                    <select wire:model.live="newTaxRate" class="po-select" @if($editingLineId) disabled @endif>
                        @foreach($taxOptions as $rate => $label)
                            <option value="{{ $rate }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="po-field">
                    <label class="po-label">Costo compra s/IVA</label>
                    <input
                        type="number"
                        step="0.0001"
                        min="0"
                        wire:model.live.debounce.300ms="newUnitCostWithoutTax"
                        class="po-input po-input-num"
                        @if($editingLineId) disabled @endif
                    >
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

            @php
                $newQtyPreview = max((float) $newQuantity, 0);
                $newUxePreview = max((float) $newPurchaseUnitFactor, 1);
                $newCostPreview = max((float) $newUnitCostWithoutTax, 0);
                $newTaxPreview = max((float) $newTaxRate, 0);

                $newBaseUnitWithoutTaxPreview =
                    $newUxePreview > 0
                        ? $newCostPreview / $newUxePreview
                        : $newCostPreview;

                $newBaseUnitWithTaxPreview =
                    $newBaseUnitWithoutTaxPreview
                    * (1 + ($newTaxPreview / 100));

                $newTotalWithoutTaxPreview =
                    $newQtyPreview * $newCostPreview;

                $newTotalWithTaxPreview =
                    $newTotalWithoutTaxPreview
                    * (1 + ($newTaxPreview / 100));

                $newBaseQtyPreview =
                    $newQtyPreview * $newUxePreview;
            @endphp

            <div class="po-cost-preview">
                <span class="po-preview-item">
                    <span class="po-preview-label">Base</span>
                    <strong>{{ number_format($newBaseQtyPreview, 2) }}</strong>
                </span>

                <span class="po-preview-item po-preview-unit">
                    <span class="po-preview-label">Unitario</span>

                    <strong>
                        $ {{ number_format($newBaseUnitWithoutTaxPreview, 4) }}
                    </strong>

                    <span>s/IVA</span>

                    <strong>
                        $ {{ number_format($newBaseUnitWithTaxPreview, 4) }}
                    </strong>

                    <span>c/IVA</span>
                </span>

                <span class="po-preview-item po-preview-total">
                    <span class="po-preview-label">Total</span>

                    <strong>
                        $ {{ number_format($newTotalWithoutTaxPreview, 2) }}
                    </strong>

                    <span>s/IVA</span>

                    <strong>
                        $ {{ number_format($newTotalWithTaxPreview, 2) }}
                    </strong>

                    <span>c/IVA</span>
                </span>
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
                            <label class="po-label">UXE</label>
                            <input
                                type="number"
                                step="0.000001"
                                min="1"
                                wire:model.live.debounce.300ms="editPurchaseUnitFactor"
                                class="po-input po-input-num"
                                title="Unidades base por unidad comprada"
                            >

                            @if($editSuggestedUxeText !== '')
                                <div class="po-hint">
                                    {{ $editSuggestedUxeText }}

                                    @if($editSuggestedUxe)
                                        <button
                                            type="button"
                                            wire:click="applyEditSuggestedUxe"
                                            class="po-suggest-btn"
                                        >
                                            Usar
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="po-field">
                            <label class="po-label">Cantidad</label>
                            <input type="number" step="0.000001" min="0" wire:model.live.debounce.300ms="editQuantity" class="po-input po-input-num">
                        </div>

                        <div class="po-field">
                            <label class="po-label">IVA</label>
                            <select wire:model.live="editTaxRate" class="po-select">
                                @foreach($taxOptions as $rate => $label)
                                    <option value="{{ $rate }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="po-field">
                            <label class="po-label">Costo compra s/IVA</label>
                            <input type="number" step="0.0001" min="0" wire:model.live.debounce.300ms="editUnitCostWithoutTax" class="po-input po-input-num">
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

                    @php
                        $editQtyPreview = max((float) $editQuantity, 0);
                        $editUxePreview = max((float) $editPurchaseUnitFactor, 1);
                        $editCostPreview = max((float) $editUnitCostWithoutTax, 0);
                        $editTaxPreview = max((float) $editTaxRate, 0);

                        $editBaseUnitWithoutTaxPreview =
                            $editUxePreview > 0
                                ? $editCostPreview / $editUxePreview
                                : $editCostPreview;

                        $editBaseUnitWithTaxPreview =
                            $editBaseUnitWithoutTaxPreview
                            * (1 + ($editTaxPreview / 100));

                        $editTotalWithoutTaxPreview =
                            $editQtyPreview * $editCostPreview;

                        $editTotalWithTaxPreview =
                            $editTotalWithoutTaxPreview
                            * (1 + ($editTaxPreview / 100));

                        $editBaseQtyPreview =
                            $editQtyPreview * $editUxePreview;
                    @endphp

                    <div class="po-cost-preview" style="padding:12px 0 0 0;">
                        <span class="po-preview-item">
                            <span class="po-preview-label">Base</span>
                            <strong>{{ number_format($editBaseQtyPreview, 2) }}</strong>
                        </span>

                        <span class="po-preview-item po-preview-unit">
                            <span class="po-preview-label">Unitario</span>

                            <strong>
                                $ {{ number_format($editBaseUnitWithoutTaxPreview, 4) }}
                            </strong>

                            <span>s/IVA</span>

                            <strong>
                                $ {{ number_format($editBaseUnitWithTaxPreview, 4) }}
                            </strong>

                            <span>c/IVA</span>
                        </span>

                        <span class="po-preview-item po-preview-total">
                            <span class="po-preview-label">Total</span>

                            <strong>
                                $ {{ number_format($editTotalWithoutTaxPreview, 2) }}
                            </strong>

                            <span>s/IVA</span>

                            <strong>
                                $ {{ number_format($editTotalWithTaxPreview, 2) }}
                            </strong>

                            <span>c/IVA</span>
                        </span>
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
                        <th style="min-width: 100px;">Unidad</th>
                        <th class="right" style="min-width: 65px;">UXE</th>
                        <th class="right" style="min-width: 95px;">Cantidad</th>
                        <th class="right" style="min-width: 70px;">IVA</th>
                        <th class="right po-th-purchase" style="min-width: 125px;">
                            <div class="po-th-title">Costo compra</div>
                            <div class="po-th-sub">por unidad de compra · s/IVA</div>
                        </th>

                        <th class="right po-th-unit" style="min-width: 135px;">
                            <div class="po-th-title">Costo unitario</div>
                            <div class="po-th-sub">por unidad base</div>
                        </th>

                        <th class="right po-th-total" style="min-width: 130px;">
                            <div class="po-th-title">Total</div>
                            <div class="po-th-sub">por partida</div>
                        </th>
                        <th class="right" style="min-width: 105px;">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($visibleLines as $line)
                        <tr>
                            <td>{{ $line['product_label'] ?: '—' }}</td>
                            <td>{{ $line['variant_label'] ?: '—' }}</td>
                            @php
                                $lineUxe = max(
                                    (float) ($line['purchase_unit_factor'] ?? 1),
                                    1
                                );

                                $linePurchaseCostWithoutTax =
                                    (float) ($line['unit_cost_without_tax'] ?? 0);

                                $lineTaxRate =
                                    (float) ($line['tax_rate'] ?? 0);

                                $lineBaseUnitCostWithoutTax =
                                    $linePurchaseCostWithoutTax / $lineUxe;

                                $lineBaseUnitCostWithTax =
                                    $lineBaseUnitCostWithoutTax
                                    * (1 + ($lineTaxRate / 100));

                                $lineTotalWithoutTax =
                                    (float) ($line['line_total_without_tax'] ?? 0);

                                $lineTotalWithTax =
                                    (float) ($line['line_total_with_tax'] ?? 0);
                            @endphp

                            <td>{{ $line['purchase_unit_label'] ?: '—' }}</td>

                            <td class="right">
                                <span class="po-uxe-chip">
                                    {{ rtrim(rtrim(number_format($lineUxe, 6, '.', ''), '0'), '.') }}
                                </span>
                            </td>

                            <td class="right">
                                <div class="po-cell-main">
                                    {{ number_format((float) $line['ordered_quantity'], 2) }}
                                </div>

                                <div class="po-cell-sub">
                                    {{ number_format((float) $line['base_quantity'], 2) }} base
                                </div>
                            </td>

                            <td class="right">
                                {{ number_format($lineTaxRate, 2) }}%
                            </td>

                            <td class="right po-money-block po-money-purchase">
                                <div class="po-money-line">
                                    $ {{ number_format($linePurchaseCostWithoutTax, 4) }}
                                    <span class="po-money-label">s/IVA</span>
                                </div>

                                <div class="po-money-context">
                                    unidad de compra
                                </div>
                            </td>

                            <td class="right po-money-block po-money-unit">
                                <div class="po-money-line">
                                    $ {{ number_format($lineBaseUnitCostWithoutTax, 4) }}
                                    <span class="po-money-label">s/IVA</span>
                                </div>

                                <div class="po-money-line">
                                    $ {{ number_format($lineBaseUnitCostWithTax, 4) }}
                                    <span class="po-money-label">c/IVA</span>
                                </div>
                            </td>

                            <td class="right po-money-block po-money-total">
                                <div class="po-money-line">
                                    $ {{ number_format($lineTotalWithoutTax, 2) }}
                                    <span class="po-money-label">s/IVA</span>
                                </div>

                                <div class="po-money-line">
                                    $ {{ number_format($lineTotalWithTax, 2) }}
                                    <span class="po-money-label">c/IVA</span>
                                </div>
                            </td>
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
