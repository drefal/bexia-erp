<x-filament-panels::page>
    <div class="space-y-6" wire:poll.15s>

    {{-- BEXIA_SERVER_MONITOR_UI_START --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    Monitor del servidor
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Configura los correos que recibirán alertas cuando el disco llegue a warning, critical o emergency.
                </p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Correos de alerta
                </label>

                <textarea
                    wire:model.defer="serverMonitorAlertEmails"
                    rows="4"
                    class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    placeholder="correo1@dominio.com, correo2@dominio.com"
                ></textarea>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Puedes separar correos por coma, punto y coma o salto de línea.
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <x-filament::button type="button" wire:click="saveServerMonitorAlertEmails">
                        Guardar correos
                    </x-filament::button>

                    <x-filament::button type="button" color="gray" wire:click="requestServerMonitorEmailTest">
                        Solicitar prueba de correo
                    </x-filament::button>
                </div>

                @if ($serverMonitorAlertFeedback)
                    <div class="mt-3 rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm text-primary-800 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200">
                        {{ $serverMonitorAlertFeedback }}
                    </div>
                @endif
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Estado actual del servidor
                    </label>

                    <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $this->serverMonitorStatusText() }}</pre>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Últimos eventos de correo
                    </label>

                    <pre class="mt-2 max-h-40 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $this->serverMonitorMailLogText() }}</pre>
                </div>
            </div>
        </div>
    </div>
    {{-- BEXIA_SERVER_MONITOR_UI_END --}}


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
                        {{ $this->lastOperation['updated_at'] ?? $this->lastOperation['finished_at'] ?? $this->lastOperation['started_at'] ?? '' }}
                    </div>
                    <div class="mt-1 text-xs">
                        Estado: <strong>{{ $this->lastOperation['status'] ?? 'N/A' }}</strong>
                    </div>
                    @if (! empty($this->lastOperation['message']))
                        <div class="mt-1 text-xs text-gray-500">
                            {{ $this->lastOperation['message'] }}
                        </div>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-sm font-semibold">Destino de respaldo</div>
                    <div class="mt-1 text-sm">DEV</div>
                    <div class="mt-1 text-xs text-gray-500">143.244.186.80</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Estado en vivo
            </x-slot>

            <x-slot name="description">
                La pantalla se actualiza automáticamente cada 15 segundos. El runner revisa solicitudes cada 5 minutos.
            </x-slot>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Estado</div>
                    <div class="mt-1 text-xl font-bold">
                        {{ strtoupper($this->lastOperation['status'] ?? 'N/A') }}
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Operación</div>
                    <div class="mt-1 text-sm font-semibold">
                        {{ $this->lastOperation['type'] ?? 'Sin operación' }}
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Actualizado</div>
                    <div class="mt-1 text-sm font-semibold">
                        {{ $this->lastOperation['updated_at'] ?? $this->lastOperation['finished_at'] ?? $this->lastOperation['started_at'] ?? 'N/A' }}
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Acción</div>
                    <button
                        type="button"
                        wire:click="$refresh"
                        class="mt-2 inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2563eb; color: #ffffff; border: 1px solid #1d4ed8;"
                    >
                        Actualizar estado ahora
                    </button>
                </div>
            </div>

            @if (! empty($this->lastOperation['message']))
                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    {{ $this->lastOperation['message'] }}
                </div>
            @endif
        </x-filament::section>

        @if ($this->isProdEnvironment())
            <x-filament::section>
                <x-slot name="heading">
                    Generar respaldo manual PROD → DEV
                </x-slot>

                <x-slot name="description">
                    Genera un paquete .tar.gz con base de datos y storage de PROD, y lo envía al servidor DEV.
                </x-slot>

                <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-900 dark:bg-warning-950 dark:text-warning-100">
                    Esta acción puede tardar varios minutos. Para habilitar el botón escribe exactamente:
                    <strong>GENERAR RESPALDO</strong>
                </div>

                <div class="mt-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            wire:model.live="backupConfirmation"
                            placeholder="GENERAR RESPALDO"
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="mt-4">
                    <button
                        type="button"
                        wire:click="requestManualBackup"
                        class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2563eb; color: #ffffff; border: 1px solid #1d4ed8;"
                    >
                        Generar respaldo manual ahora
                    </button>
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
