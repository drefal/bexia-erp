<x-filament-panels::page>
    <div class="space-y-4">
        <div style="border:1px solid #e5e7eb;background:#ffffff;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
                <div>
                    <div style="font-weight:700;color:#111827;font-size:14px;">Filtros del reporte</div>
                    <div style="font-size:12px;color:#6b7280;margin-top:2px;">Filtra por ubicación, prioridad o agrupa la sugerencia de reabastecimiento.</div>
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
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Agrupar por</label>
                    <select wire:model.live="groupBy" style="width:100%;height:38px;border:1px solid #d1d5db;border-radius:10px;padding:0 10px;font-size:13px;background:#fff;">
                        <option value="detail">Detalle</option>
                        <option value="supplier">Proveedor sugerido</option>
                        <option value="warehouse_location">Almacén / ubicación</option>
                        <option value="priority">Prioridad</option>
                    </select>
                </div>

                <div style="grid-column:span 4;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Buscar</label>
                    <input wire:model.live.debounce.400ms="search" type="text" placeholder="Producto, variante, proveedor..." style="width:100%;height:38px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font-size:13px;background:#fff;">
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Líneas mostradas</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($this->totals['rules']) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Faltantes</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($this->totals['shortages']) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Cantidad sugerida total</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($this->totals['suggested_total'], 2) }}</div>
            </div>
        </div>

        @forelse ($this->groupedRows as $group => $rows)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $group }}</div>
                    <div class="text-xs text-gray-500">{{ count($rows) }} línea(s)</div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Almacén</th>
                                <th class="px-3 py-2 text-left font-semibold">Ubicación</th>
                                <th class="px-3 py-2 text-left font-semibold">Producto</th>
                                <th class="px-3 py-2 text-left font-semibold">Variante</th>
                                <th class="px-3 py-2 text-right font-semibold">Disponible</th>
                                <th class="px-3 py-2 text-right font-semibold">Mín.</th>
                                <th class="px-3 py-2 text-right font-semibold">Máx.</th>
                                <th class="px-3 py-2 text-right font-semibold">Faltante máx.</th>
                                <th class="px-3 py-2 text-right font-semibold">UXES</th>
                                <th class="px-3 py-2 text-right font-semibold">Compra mín.</th>
                                <th class="px-3 py-2 text-right font-semibold">Múltiplo</th>
                                <th class="px-3 py-2 text-right font-semibold">Sugerido</th>
                                <th class="px-3 py-2 text-left font-semibold">Proveedor sugerido</th>
                                <th class="px-3 py-2 text-left font-semibold">Prioridad</th>
                                <th class="px-3 py-2 text-left font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['warehouse'] }}</td>
                                    <td class="px-3 py-2">{{ $row['location'] }}</td>
                                    <td class="px-3 py-2 font-medium">{{ $row['product'] }}</td>
                                    <td class="px-3 py-2">{{ $row['variant'] }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($row['available'], 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($row['min'], 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($row['max'], 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($row['base_needed'], 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($row['pack_units'], 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $row['purchase_min'] > 0 ? number_format($row['purchase_min'], 2) : '—' }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($row['purchase_multiple'], 2) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ number_format($row['suggested'], 2) }}</td>
                                    <td class="px-3 py-2">{{ $row['supplier'] }}</td>
                                    <td class="px-3 py-2">{{ $row['priority_label'] }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium
                                            @if ($row['status_color'] === 'danger') bg-red-50 text-red-700 ring-1 ring-red-600/20
                                            @elseif ($row['status_color'] === 'warning') bg-yellow-50 text-yellow-700 ring-1 ring-yellow-600/20
                                            @else bg-green-50 text-green-700 ring-1 ring-green-600/20
                                            @endif
                                        ">
                                            {{ $row['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm font-medium text-gray-900 dark:text-white">No hay líneas para mostrar</div>
                <div class="mt-1 text-sm text-gray-500">
                    Revisa filtros, reglas activas o existencias actuales.
                </div>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
