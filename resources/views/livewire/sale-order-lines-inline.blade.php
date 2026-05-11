<div class="so-lines-wrap">
    <style>

        .bexia-sale-lines-products-table tbody td:first-child,
        .bexia-sale-lines-products-table tbody td:first-child * {
            font-weight:400 !important;
        }
        .so-card{border:1px solid #d9e2ef;border-radius:14px;background:#fff;overflow:visible;}
        .so-card-header{padding:14px 16px;border-bottom:1px solid #e5edf6;}
        .so-title{font-weight:400;color:#0f172a;font-size:13px;}
        .so-subtitle{color:#64748b;font-size:11px;margin-top:2px;}
        .so-add-grid{display:grid;grid-template-columns:minmax(260px,2fr) minmax(190px,1.25fr) minmax(130px,.8fr) minmax(100px,.7fr) minmax(140px,1fr) minmax(140px,1fr) 95px;gap:10px;align-items:end;padding:14px 16px;position:relative;}
        .so-field{position:relative;min-width:0;}
        .so-label{display:block;font-size:11px;font-weight:600;color:#0f172a;margin-bottom:5px;}
        .so-input,.so-select{width:100%;height:37px;border:1px solid #cfd8e6;border-radius:8px;background:#fff;color:#0f172a;font-size:13px;padding:0 10px;outline:none;}
        .so-input-num{text-align:right;}
        .so-input:focus,.so-select:focus{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.10);}
        .so-btn{height:37px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:400;font-size:13px;padding:0 14px;cursor:pointer;box-shadow:0 8px 18px rgba(37,99,235,.18);}
        .so-btn:hover{background:#1d4ed8;}
        .so-btn-gray{background:#f1f5f9;color:#0f172a;box-shadow:none;border:1px solid #cfd8e6;}
        .so-btn-danger{background:#fee2e2;color:#b91c1c;box-shadow:none;border:1px solid #fecaca;}
        .so-results{position:absolute;z-index:60;left:0;right:0;top:62px;background:#fff;border:1px solid #cfd8e6;border-radius:10px;box-shadow:0 18px 35px rgba(15,23,42,.16);max-height:260px;overflow:auto;}
        .so-result{display:block;width:100%;text-align:left;padding:9px 10px;border:0;background:#fff;cursor:pointer;}
        .so-result:hover{background:#eff6ff;}
        .so-result-title{font-size:13px;font-weight:400;color:#0f172a;}
        .so-result-hint{font-size:11px;color:#64748b;margin-top:2px;}
        .so-table-wrap{padding:0 16px 14px 16px;overflow-x:auto;}
        .so-table{width:100%;border-collapse:collapse;font-size:12px;}
        .so-table th{padding:10px;border-bottom:1px solid #e5e7eb;background:#f8fafc;color:#0f172a;text-align:left;font-weight:800;}
        .so-table td{padding:10px;border-bottom:1px solid #f1f5f9;color:#0f172a;}
        .so-right{text-align:right;}
        .so-strong{font-weight:400;}
        .so-actions{display:flex;gap:10px;justify-content:flex-end;}
        .so-link{border:0;background:transparent;color:#2563eb;font-weight:400;cursor:pointer;font-size:12px;}
        .so-link-danger{color:#dc2626;}
        .so-totals{border-top:1px solid #e5e7eb;background:#f8fafc;padding:14px 16px;display:flex;justify-content:flex-end;}
        .so-totals-box{min-width:320px;max-width:420px;width:100%;}
        .so-total-row{display:flex;justify-content:space-between;padding:4px 0;font-size:13px;}
        .so-total-main{margin-top:8px;padding-top:8px;border-top:1px solid #cbd5e1;font-size:15px;font-weight:800;}
        .so-badge{display:inline-block;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:800;}
        .so-badge-success{background:#dcfce7;color:#166534;}
        .so-badge-warning{background:#fef9c3;color:#854d0e;}
        .so-badge-danger{background:#fee2e2;color:#991b1b;}
        .so-badge-gray{background:#f1f5f9;color:#475569;}
        .so-empty{padding:18px;text-align:center;color:#64748b;font-size:13px;}
    </style>

    <div class="so-card">
        <div class="so-card-header">
            <div class="so-title">{{ $editingLineId ? 'Editar producto' : 'Agregar producto' }}</div>
            <div class="so-subtitle">Busca el producto, elige variante si aplica, captura cantidad y precio.</div>
        </div>
        @if(! $canEditLines)
            <div style="margin:14px 16px 0 16px;border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:12px;padding:10px 12px;font-size:12px;">
                Las líneas no están editables por el estado actual del documento o porque está pendiente de aprobación.
            </div>
        @endif



        @if($canEditLines)
        <div class="so-add-grid">
            <div class="so-field">
                <label class="so-label">Producto</label>
                <input class="so-input" type="text" wire:model.live.debounce.300ms="productSearch" placeholder="Buscar producto...">

                @if(! $productId && trim($productSearch) !== '')
                    <div class="so-results">
                        @forelse($productResults as $product)
                            <button type="button" class="so-result" wire:click="selectProduct({{ $product['id'] }})">
                                <div class="so-result-title">{{ $product['label'] }}</div>
                                @if($product['hint'])
                                    <div class="so-result-hint">{{ $product['hint'] }}</div>
                                @endif
                            </button>
                        @empty
                            <div class="so-empty">Sin resultados</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="so-field">
                <label class="so-label">Variante</label>
                <select class="so-select" wire:model.live="variantId">
                    <option value="">Sin variante</option>
                    @foreach($variantOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="so-field">
                <label class="so-label">Unidad</label>
                <input class="so-input" type="text" wire:model="unitLabel">
            </div>

            <div class="so-field">
                <label class="so-label">Cantidad</label>
                <input class="so-input so-input-num" type="number" step="0.000001" min="0" wire:model.live.debounce.300ms="quantity">
            </div>

            <div class="so-field">
                <label class="so-label">Precio s/IVA</label>
                <input class="so-input so-input-num" type="number" step="0.000001" min="0" wire:model.live.debounce.300ms="unitPriceWithoutTax">
            </div>

            <div class="so-field">
                <label class="so-label">Impuesto</label>
                <select class="so-select" wire:model.live="taxRate">
                    <option value="0">Sin IVA (0%)</option>
                    <option value="8">IVA 8% (8.00%)</option>
                    <option value="16">IVA 16% (16.00%)</option>
                </select>
            </div>

            <div class="so-field">
                @if($editingLineId)
                    <button type="button" class="so-btn" wire:click="updateLine">Guardar</button>
                    <button type="button" wire:click="cancelEditLine" style="margin-left:8px;font-size:12px;color:#64748b;text-decoration:underline;">
                        Cancelar
                    </button>
                @else
                    <button type="button" class="so-btn" wire:click="addLine">Agregar</button>
                @endif
            </div>
        </div>

        @endif

        <div class="so-card-header" style="border-top:1px solid #e5edf6;">
            <div class="so-title">Productos agregados</div>
            <div class="so-subtitle">Partidas de esta venta.</div>
        </div>

        <div class="so-table-wrap">
            <table class="so-table bexia-sale-lines-products-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Variante</th>
                        <th>Unidad</th>
                        <th class="so-right">Cantidad</th>
                        <th class="so-right">Precio s/IVA</th>
                        <th class="so-right">IVA</th>
                        <th class="so-right">Precio c/IVA</th>
                        <th>Semáforo</th>
                        <th class="so-right">Importe</th>
                        <th class="so-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                        @php
                            $status = $line['margin_status'] ?? 'no_cost';
                            $badgeClass = match ($status) {
                                'success' => 'so-badge-success',
                                'warning' => 'so-badge-warning',
                                'danger' => 'so-badge-danger',
                                default => 'so-badge-gray',
                            };
                            $badgeText = match ($status) {
                                'success' => 'Precio sano',
                                'warning' => 'Margen bajo',
                                'danger' => 'Pérdida',
                                default => 'Sin costo',
                            };
                        @endphp
                        <tr>
                            <td>{{ $line['product_label'] ?? '—' }}</td>
                            <td>{{ $line['variant_label'] ?: '—' }}</td>
                            <td>{{ $line['unit_label'] ?? 'Pieza' }}</td>
                            <td class="so-right">{{ number_format((float) ($line['quantity'] ?? 0), 2) }}</td>
                            <td class="so-right">$ {{ number_format((float) ($line['unit_price_without_tax'] ?? 0), 4) }}</td>
                            <td class="so-right">{{ number_format((float) ($line['tax_rate'] ?? 0), 2) }}%</td>
                            <td class="so-right">$ {{ number_format((float) ($line['unit_price_with_tax'] ?? 0), 4) }}</td>
                            <td>
                                <span class="so-badge {{ $badgeClass }}">{{ $badgeText }}</span>
                            </td>
                            <td class="so-right so-strong">$ {{ number_format((float) ($line['line_total_with_tax'] ?? 0), 2) }}</td>
                            <td>
                                @if($canEditLines)
                                    <div class="so-actions">
                                        <button type="button" class="so-link" wire:click="editLine({{ (int) $line['id'] }})">Editar</button>
                                        <button type="button" class="so-link so-link-danger" wire:click="deleteLine({{ (int) $line['id'] }})">Eliminar</button>
                                    </div>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="so-empty">Aún no hay productos agregados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="so-totals">
            <div class="so-totals-box">
                <div class="so-total-row">
                    <span>Importe sin impuestos:</span>
                    <strong>$ {{ number_format($totals['subtotal'], 2) }}</strong>
                </div>
                <div class="so-total-row">
                    <span>IVA:</span>
                    <strong>$ {{ number_format($totals['tax'], 2) }}</strong>
                </div>
                <div class="so-total-row so-total-main">
                    <span>Total:</span>
                    <strong>$ {{ number_format($totals['total'], 2) }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
