<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Operaciones del sistema
            </x-slot>

            <x-slot name="description">
                Ambiente actual: <strong>{{ $this->environmentLabel }}</strong>. Solo superadmin.
            </x-slot>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-sm font-semibold">Ambiente</div>
                    <div class="mt-1 text-2xl font-bold">{{ $this->environmentLabel }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ config('app.url') }}</div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-sm font-semibold">Última operación</div>
                    <div class="mt-1 text-sm">
                        {{ $this->lastOperation['type'] ?? 'Sin operaciones registradas' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ $this->lastOperation['finished_at'] ?? $this->lastOperation['started_at'] ?? '' }}
                    </div>
                    <div class="mt-1 text-xs">
                        Estado: <strong>{{ $this->lastOperation['status'] ?? 'N/A' }}</strong>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-sm font-semibold">Acciones</div>
                    <div class="mt-3">
                        <x-filament::button wire:click="refreshBackupIndex" size="sm" color="gray">
                            Actualizar lista de respaldos
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </x-filament::section>

        @if ($this->isDevEnvironment())
            <x-filament::section>
                <x-slot name="heading">
                    Restaurar PROD sobre DEV
                </x-slot>

                <x-slot name="description">
                    Esta acción restaura la base y storage de PROD sobre DEV. Antes crea backup previo de DEV.
                </x-slot>

                <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-900 dark:bg-warning-950 dark:text-warning-100">
                    Para habilitar el botón escribe exactamente:
                    <strong>CLONAR PROD A DEV</strong>
                </div>

                <div class="mt-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            wire:model.live="restoreConfirmation"
                            placeholder="CLONAR PROD A DEV"
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse ($this->prodBackups as $backup)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="font-semibold">{{ $backup['name'] }}</div>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $backup['path'] }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $this->formatBytes((int) ($backup['size'] ?? 0)) }}
                                ·
                                {{ $backup['modified_at'] ?? '' }}
                            </div>
                            <div class="mt-3">
                                <x-filament::button
                                    color="danger"
                                    size="sm"
                                    wire:click="requestRestore('{{ $backup['path'] }}')"
                                >
                                    Restaurar este respaldo en DEV
                                </x-filament::button>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">
                            No hay respaldos indexados. Presiona “Actualizar lista de respaldos”.
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-filament::section class="lg:col-span-1">
                <x-slot name="heading">
                    Logs disponibles
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

                <pre
                    class="max-h-[650px] overflow-auto rounded-lg p-4 text-xs leading-relaxed"
                    style="background-color: #0f172a; color: #e5e7eb; border: 1px solid #334155; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"
                >{{ $this->selectedLogContent }}</pre>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
