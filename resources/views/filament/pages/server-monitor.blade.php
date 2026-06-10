<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Estado del servidor {{ $runtime['name'] ?? '' }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Monitor operativo del servidor, respaldos y correo de alertas.
            </p>

            <div class="mt-3 grid gap-2 text-sm text-gray-600 dark:text-gray-300 md:grid-cols-3">
                <div><strong>Ambiente:</strong> {{ $runtime['name'] ?? 'N/D' }}</div>
                <div><strong>APP URL:</strong> {{ $runtime['app_url'] ?? 'N/D' }}</div>
                <div><strong>Ruta:</strong> {{ $runtime['app_dir'] ?? 'N/D' }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Disco</h3>
                <pre class="overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $disk }}</pre>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Memoria / uptime</h3>
                <pre class="overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $memory }}

{{ $uptime }}</pre>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Docker</h3>
            <pre class="overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $docker }}</pre>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Correo del monitor</h3>
            <pre class="overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $monitorMailLog }}</pre>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Última operación</h3>
                <pre class="overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $systemOpsState }}</pre>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Backups PROD recibidos</h3>
                <pre class="overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $prodBackupsState }}</pre>
            </div>
        </div>
    </div>
</x-filament-panels::page>
