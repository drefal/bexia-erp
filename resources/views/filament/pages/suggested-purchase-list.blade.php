<x-filament-panels::page>
    <div class="space-y-4">
        <div style="border:1px solid #e5e7eb;background:#ffffff;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
                <div>
                    <div style="font-weight:700;color:#111827;font-size:14px;">Parámetros de compra</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                        El presupuesto se reparte por urgencia. Si no alcanza para comprar todo, Bexia ajusta las cantidades sugeridas.
                    </div>
                </div>

                <label style="display:inline-flex;align-items:center;gap:8px;border:1px solid #dbe3ef;border-radius:999px;padding:7px 11px;font-size:12px;color:#111827;background:#f8fafc;white-space:nowrap;">
                    <input wire:model.live="onlyShortages" type="checkbox" style="border-radius:4px;">
                    Solo faltantes
                </label>
            </div>

            <div style="display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px;align-items:end;">
                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Almacén</label>
                    <select wire:model.live="warehouseId" style="width:100%;height:38px;border:1px solid #d1d5db;border-radius:10px;padding:0 10px;font-size:13px;background:#fff;">
                        <option value="">Todos</option>
                        @foreach ($this->warehouseOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Ubicación</label>
                    <select wire:model.live="locationId" style="width:100%;height:38px;border:1px solid #d1d5db;border-radius:10px;padding:0 10px;font-size:13px;background:#fff;">
                        <option value="">Todas</option>
                        @foreach ($this->locationOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Prioridad</label>
                    <select wire:model.live="priority" style="width:100%;height:38px;border:1px solid #d1d5db;border-radius:10px;padding:0 10px;font-size:13px;background:#fff;">
                        <option value="">Todas</option>
                        <option value="critical">Crítica</option>
                        <option value="high">Alta</option>
                        <option value="normal">Normal</option>
                        <option value="low">Baja</option>
                    </select>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Presupuesto con IVA</label>
                    <input wire:model.live.debounce.300ms="budgetAmount" type="text" inputmode="decimal" placeholder="Ej. 1000.00" style="width:100%;height:38px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font-size:13px;background:#fff;">
                </div>

                <div style="grid-column:span 4;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Buscar</label>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Producto, variante, proveedor..." style="width:100%;height:38px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font-size:13px;background:#fff;">
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">Presupuesto con IVA</div>
                <div class="mt-1 text-2xl font-semibold">
                    {{ $this->totals['budget'] > 0 ? '$ ' . number_format($this->totals['budget'], 2) : 'Sin límite' }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">Necesidad total</div>
                <div class="mt-1 text-2xl font-semibold">$ {{ number_format($this->totals['full_total'], 2) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">Compra sugerida ahora</div>
                <div class="mt-1 text-2xl font-semibold">$ {{ number_format($this->totals['included_total'], 2) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-sm text-gray-500">Pendiente si alcanza</div>
                <div class="mt-1 text-2xl font-semibold">$ {{ number_format($this->totals['out_total'], 2) }}</div>
            </div>
        </div>

        @forelse ($this->groupedRows as $supplier => $rows)
            @php
                $buyRows = collect($rows)->filter(fn ($row) => (float) ($row['approved_quantity'] ?? 0) > 0);
                $pendingRows = collect($rows)->filter(fn ($row) => (float) ($row['pending_quantity'] ?? 0) > 0);
                $buySubtotal = $buyRows->sum('approved_total');
                $pendingSubtotal = $pendingRows->sum('pending_total');
                $hasBudget = $this->totals['budget'] > 0;
            @endphp

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="font-semibold text-gray-900">{{ $supplier }}</div>
                            <div class="text-xs text-gray-500">{{ count($rows) }} producto(s)</div>
                        </div>
                        <div class="text-right text-xs text-gray-600">
                            <div>Comprar ahora: <strong>$ {{ number_format($buySubtotal, 2) }}</strong></div>
                            @if ($hasBudget)
                                <div>Pendiente: <strong>$ {{ number_format($pendingSubtotal, 2) }}</strong></div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($buyRows->isNotEmpty())
                    <div class="px-4 pt-4 pb-2 text-sm font-semibold text-gray-900">
                        Compra sugerida ahora
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Producto</th>
                                    <th class="px-3 py-2 text-left font-semibold">Variante</th>
                                    <th class="px-3 py-2 text-left font-semibold">Ubicación</th>
                                    <th class="px-3 py-2 text-right font-semibold">Disponible</th>
                                    <th class="px-3 py-2 text-right font-semibold">Sugerido total</th>
                                    <th class="px-3 py-2 text-right font-semibold">Pedir ahora</th>
                                    <th class="px-3 py-2 text-right font-semibold">Costo c/IVA</th>
                                    <th class="px-3 py-2 text-right font-semibold">Importe ahora</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($buyRows as $row)
                                    <tr>
                                        <td class="px-3 py-2 font-medium">{{ $row['product'] }}</td>
                                        <td class="px-3 py-2">{{ $row['variant'] }}</td>
                                        <td class="px-3 py-2">{{ $row['location'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($row['available'], 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($row['full_suggested'], 2) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($row['approved_quantity'], 2) }}</td>
                                        <td class="px-3 py-2 text-right">$ {{ number_format($row['unit_cost'], 4) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">$ {{ number_format($row['approved_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($hasBudget && $pendingRows->isNotEmpty())
                    <div class="border-t border-gray-200 px-4 pt-4 pb-2 text-sm font-semibold text-gray-900">
                        Pendiente si alcanza presupuesto
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Producto</th>
                                    <th class="px-3 py-2 text-left font-semibold">Variante</th>
                                    <th class="px-3 py-2 text-left font-semibold">Ubicación</th>
                                    <th class="px-3 py-2 text-right font-semibold">Disponible</th>
                                    <th class="px-3 py-2 text-right font-semibold">Pendiente</th>
                                    <th class="px-3 py-2 text-right font-semibold">Costo c/IVA</th>
                                    <th class="px-3 py-2 text-right font-semibold">Importe pendiente</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($pendingRows as $row)
                                    <tr class="bg-gray-50 text-gray-600">
                                        <td class="px-3 py-2 font-medium">{{ $row['product'] }}</td>
                                        <td class="px-3 py-2">{{ $row['variant'] }}</td>
                                        <td class="px-3 py-2">{{ $row['location'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($row['available'], 2) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($row['pending_quantity'], 2) }}</td>
                                        <td class="px-3 py-2 text-right">$ {{ number_format($row['unit_cost'], 4) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">$ {{ number_format($row['pending_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                <div class="text-sm font-medium text-gray-900">No hay productos sugeridos para compra</div>
                <div class="mt-1 text-sm text-gray-500">
                    Revisa reglas de reabastecimiento, filtros o existencias actuales.
                </div>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
