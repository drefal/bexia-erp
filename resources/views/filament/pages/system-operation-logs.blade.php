<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Logs operativos del servidor
            </x-slot>

            <x-slot name="description">
                Solo superadmin. Muestra logs técnicos de respaldos, clonación y validaciones.
            </x-slot>

            <div class="text-sm text-gray-600 dark:text-gray-300">
                Carpeta privada:
                <code>storage/app/private/system-ops/logs</code>
            </div>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-filament::section class="lg:col-span-1">
                <x-slot name="heading">
                    Archivos disponibles
                </x-slot>

                <div class="space-y-2">
                    @forelse ($this->getLogs() as $log)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <button
                                type="button"
                                wire:click="selectLog('{{ $log['name'] }}')"
                                class="w-full text-left text-sm font-medium text-primary-600 hover:underline"
                            >
                                {{ $log['name'] }}
                            </button>

                            <div class="mt-1 text-xs text-gray-500">
                                {{ $this->formatBytes($log['size']) }}
                                ·
                                {{ \Carbon\Carbon::createFromTimestamp($log['modified_at'])->format('Y-m-d H:i:s') }}
                            </div>

                            <div class="mt-2">
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    wire:click="downloadLog('{{ $log['name'] }}')"
                                >
                                    Descargar
                                </x-filament::button>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">
                            No hay logs disponibles todavía.
                        </div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">
                    Vista del log
                </x-slot>

                @if ($selectedLog)
                    <div class="mb-3 text-sm font-medium">
                        {{ $selectedLog }}
                    </div>
                @endif

                <pre class="max-h-[650px] overflow-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed text-gray-100">{{ $this->selectedLogContent }}</pre>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
