<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:poll.600s>
            <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Últimos movimientos de tesorería</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Lectura rápida de entradas y salidas recientes.</p>
                </div>
                <p class="text-xs text-gray-400">Actualizado {{ $updatedAt }}</p>
            </div>

            @if ($rows->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700">
                    No hay movimientos de tesorería registrados.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3">Caja / cuenta</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Referencia</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row->account_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', $row->type ?? '-') }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row->reference ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}</td>
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
