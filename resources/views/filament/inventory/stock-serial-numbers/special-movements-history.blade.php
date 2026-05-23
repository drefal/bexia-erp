@php
    $rows = $rows ?? [];
    $typeLabels = $typeLabels ?? [];
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Historial especial de series
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Vista solo lectura de movimientos especiales de números de serie.
                </p>
            </div>

            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                Solo lectura
            </span>
        </div>

        <div class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
            Esta base prepara los movimientos especiales de serie. Todavía no cambia estados, ubicaciones ni existencias.
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
            Tipos disponibles
        </h3>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach($typeLabels as $key => $label)
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $label }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $key }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
            Últimos movimientos registrados
        </h3>

        @if(empty($rows))
            <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                Todavía no hay movimientos especiales registrados.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Serie anterior</th>
                            <th class="px-3 py-2">Serie nueva</th>
                            <th class="px-3 py-2">Producto</th>
                            <th class="px-3 py-2">Origen</th>
                            <th class="px-3 py-2">Destino</th>
                            <th class="px-3 py-2">Motivo</th>
                            <th class="px-3 py-2">Usuario</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($rows as $row)
                            <tr class="text-gray-700 dark:text-gray-200">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['created_at'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['movement_type_label'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['serial_number_before'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['serial_number_after'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['product_label'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['source_label'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['destination_label'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['reason'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['created_by_label'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
