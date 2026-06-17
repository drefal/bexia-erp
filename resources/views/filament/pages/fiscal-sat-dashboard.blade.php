<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900">
                Fiscal SAT
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Repositorio fiscal para administrar CFDI emitidos, recibidos, descargas SAT, conciliación e impuestos estimados por empresa.
            </p>
            <div class="mt-4 rounded-lg bg-yellow-50 p-4 text-sm text-yellow-900">
                Base inicial: todavía no hay conexión automática con SAT. Primero se prepara estructura, permisos y repositorio.
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Empresas permitidas</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $stats['companies_count'] ?? 0 }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">CFDI administrados</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $stats['documents_count'] ?? 0 }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">CFDI emitidos</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $stats['issued_count'] ?? 0 }}</div>
                <div class="mt-1 text-xs text-gray-500">$ {{ number_format((float) ($stats['issued_total'] ?? 0), 2) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">CFDI recibidos</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $stats['received_count'] ?? 0 }}</div>
                <div class="mt-1 text-xs text-gray-500">$ {{ number_format((float) ($stats['received_total'] ?? 0), 2) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Siguientes fases</h3>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-700">
                    <strong>V1:</strong> carga manual de XML y repositorio fiscal.
                </div>
                <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-700">
                    <strong>V2:</strong> descarga masiva SAT con e.firma/certificados cifrados.
                </div>
                <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-700">
                    <strong>V3:</strong> conciliación contra Ventas, Compras, CxC y CxP.
                </div>
                <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-700">
                    <strong>V4:</strong> impuestos estimados y conexión con Bexia Insights AI.
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
