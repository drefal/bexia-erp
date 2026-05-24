<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            <div class="font-semibold text-gray-900 dark:text-white">
                Diagnóstico de método efectivo de costeo
            </div>

            <div class="mt-2">
                Esta pantalla es solo informativa. No recalcula costos ni modifica movimientos.
                La prioridad usada es: producto o variante, categoría, empresa y sistema.
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
