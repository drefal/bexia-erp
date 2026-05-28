<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:poll.60s>
            <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Cajas en tránsito</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Solicitudes aprobadas/pendientes que aún no han sido aplicadas. Total: <strong>$ {{ number_format($total, 2) }} MXN</strong>
                    </p>
                </div>
                <p class="text-xs text-gray-400">Actualizado {{ $updatedAt }}</p>
            </div>

            @if ($rows->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 dark:border-gray-700">
                    No hay efectivo en tránsito.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3">Folio</th>
                                <th class="px-4 py-3">Origen</th>
                                <th class="px-4 py-3">Destino</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row->number ?? ('#' . $row->id) }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row->source_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row->destination_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', $row->status ?? '-') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $row->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
