<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:poll.600s>
            <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Cajas operativas</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Columnas compactas por saldo relativo. Total: <strong>$ {{ number_format($total, 2) }} MXN</strong>
                    </p>
                </div>
                <p class="text-xs text-gray-400">Actualizado {{ $updatedAt }}</p>
            </div>

            @if ($columns->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700">
                    No hay cajas operativas configuradas para esta empresa.
                </div>
            @else
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px;">
                    @foreach ($columns as $column)
                        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div style="display:flex; align-items:flex-end; justify-content:center; height:130px; border-radius:14px; background:rgba(248,250,252,.95); padding:10px;">
                                <div
                                    style="
                                        width: 100%;
                                        height: {{ $column['percent'] }}%;
                                        min-height: 8px;
                                        border-radius: 12px 12px 6px 6px;
                                        background: {{ $column['color'] }};
                                        transition: height .35s ease;
                                    "
                                    title="{{ $column['money'] }} MXN"
                                ></div>
                            </div>

                            <div class="mt-3">
                                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $column['name'] }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $column['scope_label'] }}</p>
                                <p class="mt-2 text-lg font-bold text-gray-950 dark:text-white">{{ $column['money'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $column['percent'] }}% del máximo visible</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
