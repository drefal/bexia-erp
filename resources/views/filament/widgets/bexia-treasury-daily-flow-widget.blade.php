<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:poll.60s>
            <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Flujo de efectivo del día</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Entradas y salidas agrupadas por hora.</p>
                </div>
                <p class="text-xs text-gray-400">Actualizado {{ $updatedAt }}</p>
            </div>

            <div class="space-y-2">
                @foreach ($series as $row)
                    @php
                        $inPercent = $max > 0 ? min(100, round(($row['in'] / $max) * 100, 2)) : 0;
                        $outPercent = $max > 0 ? min(100, round(($row['out'] / $max) * 100, 2)) : 0;
                    @endphp

                    @if ($row['in'] > 0 || $row['out'] > 0)
                        <div class="grid grid-cols-12 items-center gap-2 text-xs">
                            <div class="col-span-2 font-medium text-gray-600 dark:text-gray-300">{{ $row['hour'] }}</div>
                            <div class="col-span-5">
                                <div class="h-3 rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-3 rounded-full bg-emerald-500" style="width: {{ $inPercent }}%"></div>
                                </div>
                                <p class="mt-1 text-gray-500">Entrada $ {{ number_format($row['in'], 2) }}</p>
                            </div>
                            <div class="col-span-5">
                                <div class="h-3 rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-3 rounded-full bg-rose-500" style="width: {{ $outPercent }}%"></div>
                                </div>
                                <p class="mt-1 text-gray-500">Salida $ {{ number_format($row['out'], 2) }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            @if ($series->filter(fn ($row) => $row['in'] > 0 || $row['out'] > 0)->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 dark:border-gray-700">
                    Sin movimientos de efectivo registrados hoy.
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
