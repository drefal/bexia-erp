@php
    $summary = $summary ?? [];
    $totals = $summary['totals'] ?? [];
    $rows = $summary['rows'] ?? [];
    $statusCounts = $summary['status_counts'] ?? [];
    $warnings = $summary['warnings'] ?? [];
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Conciliación de series vs existencias
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Esta vista es solo lectura. Compara las series disponibles contra la existencia registrada en el sistema.
                </p>
            </div>

            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                Solo lectura
            </span>
        </div>

        <dl class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Grupos</dt>
                <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $totals['groups'] ?? 0 }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Diferencias</dt>
                <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $totals['mismatches'] ?? 0 }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Series disponibles</dt>
                <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $totals['available_serials'] ?? 0 }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Existencia registrada</dt>
                <dd class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) ($totals['quant_quantity'] ?? 0), 2) }}</dd>
            </div>
        </dl>
    </div>

    @if(! empty($warnings))
        <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
            <div class="font-semibold">Alertas de consistencia</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
            Estados de números de serie
        </h3>

        @if(empty($statusCounts))
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay estados para mostrar.</p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                @foreach($statusCounts as $item)
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ match(strtolower((string) ($item['status'] ?? ''))) {
                                'available' => 'Disponible',
                                'sold' => 'Vendido',
                                'used' => 'Usado',
                                'unavailable' => 'No disponible',
                                default => ($item['status'] ?: 'Sin estado'),
                            } }}
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $item['total'] }}
                        </dd>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
            Series disponibles vs existencia registrada
        </h3>

        @if(empty($rows))
            <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                No hay series disponibles con almacén y ubicación para comparar.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Producto</th>
                            <th class="px-3 py-2">Variante</th>
                            <th class="px-3 py-2">Lote</th>
                            <th class="px-3 py-2">Almacén</th>
                            <th class="px-3 py-2">Ubicación</th>
                            <th class="px-3 py-2 text-right">Series disp.</th>
                            <th class="px-3 py-2 text-right">Existencia sistema</th>
                            <th class="px-3 py-2 text-right">Reservado</th>
                            <th class="px-3 py-2 text-right">Diferencia</th>
                            <th class="px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($rows as $row)
                            <tr class="text-gray-700 dark:text-gray-200">
                                <td class="px-3 py-2">{{ $row['product_label'] }}</td>
                                <td class="px-3 py-2">{{ $row['variant_label'] }}</td>
                                <td class="px-3 py-2">{{ $row['lot_label'] }}</td>
                                <td class="px-3 py-2">{{ $row['warehouse_label'] }}</td>
                                <td class="px-3 py-2">{{ $row['location_label'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['available_serials'] }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $row['quant_quantity'], 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $row['reserved_quantity'], 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $row['difference'], 2) }}</td>
                                <td class="px-3 py-2">
                                    @if($row['status'] === 'ok')
                                        <span class="rounded-full bg-success-100 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/20 dark:text-success-300">
                                            OK
                                        </span>
                                    @else
                                        <span class="rounded-full bg-warning-100 px-2 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/20 dark:text-warning-300">
                                            Revisar
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
