<div>
    <div style="border:1px solid #dbe3ef;border-radius:14px;background:#ffffff;overflow:hidden;">
        <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;background:#f8fafc;">
            <div style="font-weight:700;font-size:15px;">Agregar producto</div>
            <div style="font-size:13px;color:#64748b;">Busca el producto, elige variante si aplica, captura cantidad y costo.</div>
        </div>

        <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;">
            <div style="display:grid;grid-template-columns:2fr 1.3fr .8fr .8fr 1fr 1fr auto;gap:10px;align-items:start;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Producto</label>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="productSearch"
                        placeholder="Buscar producto..."
                        style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px;"
                    >

                    @if($product_id)
                        <div style="margin-top:6px;display:flex;align-items:center;justify-content:space-between;gap:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:8px;padding:7px 9px;font-size:12px;">
                            <span style="font-weight:700;">{{ $productSearch }}</span>
                            <button type="button" wire:click="clearProduct" style="color:#dc2626;font-weight:700;">Quitar</button>
                        </div>
                    @elseif(strlen(trim((string) $productSearch)) >= 2)
                        <div style="margin-top:6px;border:1px solid #e5e7eb;border-radius:8px;max-height:210px;overflow:auto;background:#fff;">
                            @forelse($this->productOptions as $id => $label)
                                <button
                                    type="button"
                                    wire:click="selectProduct({{ $id }})"
                                    style="display:block;width:100%;text-align:left;padding:9px 10px;border:0;border-bottom:1px solid #f1f5f9;background:#ffffff;cursor:pointer;"
                                >
                                    {{ $label }}
                                </button>
                            @empty
                                <div style="padding:10px;color:#64748b;font-size:13px;">Sin resultados.</div>
                            @endforelse
                        </div>
                    @endif

                    @error('product_id') <div style="color:#dc2626;font-size:12px;">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Variante</label>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="variantSearch"
                        placeholder="{{ $product_id ? 'Buscar variante' : 'Selecciona producto primero' }}"
                        @disabled(! $product_id)
                        style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px;{{ ! $product_id ? 'background:#f8fafc;color:#94a3b8;' : '' }}"
                    >

                    @if($product_id && $product_variant_id)
                        <div style="margin-top:6px;display:flex;align-items:center;justify-content:space-between;gap:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:8px;padding:7px 9px;font-size:12px;">
                            <span style="font-weight:700;">{{ $variantSearch }}</span>
                            <button type="button" wire:click="clearVariant" style="color:#dc2626;font-weight:700;">Quitar</button>
                        </div>
                    @elseif($product_id)
                        <div style="margin-top:6px;border:1px solid #e5e7eb;border-radius:8px;max-height:210px;overflow:auto;background:#fff;">
                            <button
                                type="button"
                                wire:click="clearVariant"
                                style="display:block;width:100%;text-align:left;padding:9px 10px;border:0;border-bottom:1px solid #f1f5f9;background:#ffffff;cursor:pointer;"
                            >
                                Sin variante
                            </button>

                            @forelse($this->variantOptions as $id => $label)
                                <button
                                    type="button"
                                    wire:click="selectVariant({{ $id }})"
                                    style="display:block;width:100%;text-align:left;padding:9px 10px;border:0;border-bottom:1px solid #f1f5f9;background:#ffffff;cursor:pointer;"
                                >
                                    {{ $label }}
                                </button>
                            @empty
                                <div style="padding:10px;color:#64748b;font-size:13px;">Sin variantes.</div>
                            @endforelse
                        </div>
                    @endif
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Unidad</label>
                    <select wire:model.live="purchase_unit_type" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px;">
                        @foreach($this->purchaseUnitOptions() as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Cantidad</label>
                    <input type="number" step="0.000001" wire:model.defer="requested_quantity" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px;text-align:right;">
                    @if(($purchase_unit_type ?? 'piece') === 'box')
                        <div style="margin-top:5px;font-size:11px;color:#64748b;">
                            Equivale a {{ number_format((float) $requested_quantity * (float) $purchase_unit_factor, 2) }} pzas
                        </div>
                    @endif
                    @error('requested_quantity') <div style="color:#dc2626;font-size:12px;">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Costo s/IVA</label>
                    <input type="number" step="0.0001" wire:model.defer="unit_cost_without_tax" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px;text-align:right;">
                    @error('unit_cost_without_tax') <div style="color:#dc2626;font-size:12px;">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Impuesto</label>
                    <select wire:model.defer="tax_rate" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px;">
                        @foreach($this->purchaseTaxOptions() as $rate => $label)
                            <option value="{{ $rate }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('tax_rate') <div style="color:#dc2626;font-size:12px;">{{ $message }}</div> @enderror
                </div>

                <div style="display:flex;gap:8px;padding-top:22px;">
                    <button type="button" wire:click="saveLine" style="background:#2563eb;color:#fff;border:0;border-radius:8px;padding:9px 14px;font-weight:700;box-shadow:0 6px 16px rgba(37,99,235,.25);">
                        {{ $editingLineId ? 'Actualizar' : 'Agregar' }}
                    </button>

                    @if($editingLineId)
                        <button type="button" wire:click="cancelEdit" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;font-weight:600;">
                            Cancelar
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb;background:#fbfdff;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <div style="font-weight:700;font-size:14px;">Productos agregados</div>
                    <div style="font-size:12px;color:#64748b;">Partidas de esta solicitud.</div>
                </div>

                <input
                    type="text"
                    wire:model.live.debounce.400ms="lineSearch"
                    placeholder="Buscar en productos agregados..."
                    style="width:100%;max-width:360px;border:1px solid #d1d5db;border-radius:8px;padding:8px;"
                >
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:separate;border-spacing:0;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Producto</th>
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Variante</th>
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Unidad</th>
                        <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;">Cantidad</th>
                        <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;">Cant. base</th>
                        <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;">Costo s/IVA</th>
                        <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;">IVA</th>
                        <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;">Costo c/IVA</th>
                        <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;">Importe</th>
                        <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->lines as $line)
                        <tr style="background:#ffffff;">
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:600;">{{ $line->product_label }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#475569;">{{ $line->variant_label ?: '—' }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">{{ $line->purchase_unit_label ?? 'Pieza' }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right;">{{ number_format((float) $line->requested_quantity, 2) }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right;">{{ number_format((float) ($line->base_quantity ?? $line->requested_quantity), 2) }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right;">$ {{ number_format((float) $line->unit_cost_without_tax, 4) }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right;">{{ number_format((float) $line->tax_rate, 2) }}%</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right;">$ {{ number_format((float) $line->unit_cost_with_tax, 4) }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:800;">$ {{ number_format((float) $line->line_total_with_tax, 2) }}</td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right;white-space:nowrap;">
                                <button type="button" wire:click="editLine({{ $line->id }})" style="color:#2563eb;font-weight:700;">Editar</button>
                                <button type="button" wire:click="confirmDeleteLine({{ $line->id }})" style="color:#dc2626;font-weight:700;margin-left:10px;">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="padding:22px;text-align:center;color:#64748b;background:#ffffff;">
                                Sin productos agregados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @php($totals = $this->totals)
        <div style="border-top:1px solid #e5e7eb;background:#f8fafc;padding:14px 16px;">
            <div style="display:flex;justify-content:flex-end;">
                <div style="min-width:320px;max-width:420px;width:100%;">
                    <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                        <span style="color:#475569;">Importe sin impuestos:</span>
                        <strong>$ {{ number_format($totals['subtotal'], 2) }}</strong>
                    </div>

                    <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                        <span style="color:#475569;">IVA:</span>
                        <strong>$ {{ number_format($totals['tax'], 2) }}</strong>
                    </div>

                    <div style="display:flex;justify-content:space-between;margin-top:8px;padding-top:8px;border-top:1px solid #cbd5e1;font-size:15px;">
                        <span style="font-weight:700;">Total:</span>
                        <strong>$ {{ number_format($totals['total'], 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>


    @if($deleteLineId)
        <div
            style="
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(15, 23, 42, .45);
                backdrop-filter: blur(1px);
                padding: 20px;
            "
            wire:click.self="cancelDeleteLine"
        >
            <div
                style="
                    width: min(520px, 100%);
                    background: #ffffff;
                    border-radius: 18px;
                    box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
                    padding: 28px;
                    border: 1px solid #e2e8f0;
                "
            >
                <div
                    style="
                        font-size: 18px;
                        line-height: 1.4;
                        font-weight: 800;
                        color: #0f172a;
                        margin-bottom: 12px;
                    "
                >
                    Eliminar producto
                </div>

                <div
                    style="
                        font-size: 15px;
                        line-height: 1.6;
                        color: #475569;
                        margin-bottom: 24px;
                    "
                >
                    ¿Eliminar <strong style="color:#334155;">{{ $deleteLineLabel }}</strong> de la solicitud?
                </div>

                <div
                    style="
                        display: flex;
                        justify-content: flex-end;
                        gap: 12px;
                    "
                >
                    <button
                        type="button"
                        wire:click="cancelDeleteLine"
                        style="
                            min-height: 42px;
                            border-radius: 12px;
                            border: 1px solid #cbd5e1;
                            background: #ffffff;
                            color: #0f172a;
                            padding: 0 18px;
                            font-weight: 800;
                            cursor: pointer;
                        "
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="deleteConfirmedLine"
                        wire:loading.attr="disabled"
                        style="
                            min-height: 42px;
                            border-radius: 12px;
                            border: 0;
                            background: #dc2626;
                            color: #ffffff;
                            padding: 0 18px;
                            font-weight: 800;
                            cursor: pointer;
                            box-shadow: 0 12px 22px rgba(220, 38, 38, .25);
                        "
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
