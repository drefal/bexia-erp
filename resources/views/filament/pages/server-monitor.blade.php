<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    Monitor del servidor
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Monitorea espacio en disco, inodos y registra alertas del servidor. El monitor externo corre por cron cada 15 minutos.
                </p>
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

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Última alerta
                    </label>

                    <pre class="mt-2 max-h-32 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $this->serverMonitorAlertText() }}</pre>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                    Estado actual
                </h3>

                <pre class="mt-3 max-h-96 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $this->serverMonitorStatusText() }}</pre>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                    Eventos recientes del monitor
                </h3>

                <pre class="mt-3 max-h-96 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $this->serverMonitorAppLogText() }}</pre>
            </div>
        </div>
    </div>
</x-filament-panels::page>
