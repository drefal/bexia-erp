<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900">
                Importar XML CFDI
            </h2>

            <p class="mt-2 text-sm text-gray-600">
                Carga manual de CFDI emitidos o recibidos. Esta opción alimenta el repositorio fiscal de Bexia y será la base para la descarga automática SAT.
            </p>

            <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                Selecciona la empresa, indica si el CFDI fue emitido o recibido, y sube el archivo XML.
            </div>
        </div>

        <form wire:submit="importXml" class="space-y-6">
            {{ $this->form }}

            <div class="flex items-center gap-3">
                <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                    Importar XML
                </x-filament::button>
            </div>
        </form>

        @if ($lastImportResult)
            <div class="rounded-xl border border-green-200 bg-green-50 p-6 text-sm text-green-900">
                <h3 class="font-semibold">
                    Último XML importado
                </h3>

                <div class="mt-3 grid gap-2 md:grid-cols-2">
                    <div><strong>UUID:</strong> {{ $lastImportResult['uuid'] ?? '' }}</div>
                    <div><strong>Tipo:</strong> {{ $lastImportResult['cfdi_type_label'] ?? ($lastImportResult['cfdi_type'] ?? '') }}</div>
                    <div><strong>Dirección:</strong> {{ $lastImportResult['direction_label'] ?? ($lastImportResult['direction'] ?? '') }}</div>
                    <div><strong>Total:</strong> ${{ number_format((float) ($lastImportResult['total'] ?? 0), 2) }}</div>
                    <div><strong>RFC emisor:</strong> {{ $lastImportResult['issuer_rfc'] ?? '' }}</div>
                    <div><strong>RFC receptor:</strong> {{ $lastImportResult['receiver_rfc'] ?? '' }}</div>
                    <div><strong>Conceptos:</strong> {{ $lastImportResult['concepts_count'] ?? 0 }}</div>
                    <div><strong>Impuestos globales:</strong> {{ $lastImportResult['global_taxes_count'] ?? ($lastImportResult['taxes_count'] ?? 0) }}</div>
                    <div><strong>Impuestos en conceptos:</strong> {{ $lastImportResult['concept_taxes_count'] ?? 0 }}</div>
                </div>

                <div class="mt-4 rounded-lg bg-white/70 p-3 text-xs text-green-950">
                    Nota: “Impuestos globales” corresponde al bloque general de impuestos del comprobante. Los impuestos por partida se conservan dentro de cada concepto.
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
