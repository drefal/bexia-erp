<x-filament-panels::page>
    @php
        $SatLabel = \App\Support\FiscalSat\SatCatalogLabelService::class;

        $record->loadMissing(['company', 'concepts', 'taxes', 'importedBy']);

        $directionLabel = $SatLabel::direction($record->direction);
        $typeLabel = $SatLabel::cfdiType($record->cfdi_type);

        $conceptTaxCount = $record->concepts->sum(function ($concept) {
            return is_array($concept->taxes) ? count($concept->taxes) : 0;
        });
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        CFDI {{ $typeLabel }}
                    </h2>

                    <p class="mt-2 text-sm text-gray-600">
                        UUID: <span class="font-mono">{{ $record->uuid }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">
                        {{ $directionLabel }}
                    </span>

                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                        {{ $record->status }}
                    </span>

                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                        Versión {{ $record->version ?: '-' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Total</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">
                    ${{ number_format((float) $record->total, 2) }}
                </div>
                <div class="mt-1 text-xs text-gray-500">{{ $record->currency ?: 'MXN' }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Subtotal</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">
                    ${{ number_format((float) $record->subtotal, 2) }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">IVA / impuestos trasladados</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">
                    ${{ number_format((float) $record->total_transferred_taxes, 2) }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Retenciones</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">
                    ${{ number_format((float) $record->total_withheld_taxes, 2) }}
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Emisor</h3>

                <div class="mt-4 space-y-2 text-sm">
                    <div><strong>RFC:</strong> {{ $record->issuer_rfc ?: '-' }}</div>
                    <div><strong>Nombre:</strong> {{ $record->issuer_name ?: '-' }}</div>
                    <div><strong>Régimen fiscal:</strong> {{ $SatLabel::label('tax_regime', data_get($record->metadata, 'issuer_regimen_fiscal')) }}</div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Receptor</h3>

                <div class="mt-4 space-y-2 text-sm">
                    <div><strong>RFC:</strong> {{ $record->receiver_rfc ?: '-' }}</div>
                    <div><strong>Nombre:</strong> {{ $record->receiver_name ?: '-' }}</div>
                    <div><strong>Uso CFDI:</strong> {{ $SatLabel::label('cfdi_usage', $record->usage_cfdi) }}</div>
                    <div><strong>Régimen fiscal:</strong> {{ $SatLabel::label('tax_regime', data_get($record->metadata, 'receiver_regimen_fiscal')) }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Datos del comprobante</h3>

            <div class="mt-4 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                <div><strong>Empresa Bexia:</strong> {{ $record->company?->name ?: '-' }}</div>
                <div><strong>Fecha emisión:</strong> {{ $record->issued_at?->format('d/m/Y H:i') ?: '-' }}</div>
                <div><strong>Fecha timbrado:</strong> {{ $record->certified_at?->format('d/m/Y H:i') ?: '-' }}</div>
                <div><strong>Forma pago:</strong> {{ $SatLabel::label('payment_form', $record->payment_form) }}</div>
                <div><strong>Método pago:</strong> {{ $SatLabel::label('payment_method', $record->payment_method) }}</div>
                <div><strong>Serie:</strong> {{ data_get($record->metadata, 'serie', '-') }}</div>
                <div><strong>Folio:</strong> {{ data_get($record->metadata, 'folio', '-') }}</div>
                <div><strong>Lugar expedición:</strong> {{ data_get($record->metadata, 'lugar_expedicion', '-') }}</div>
                <div><strong>Origen:</strong> {{ $record->source ?: '-' }}</div>
                <div><strong>Importado:</strong> {{ $record->imported_at?->format('d/m/Y H:i') ?: '-' }}</div>
                <div><strong>Importado por:</strong> {{ $record->importedBy?->name ?: '-' }}</div>
                <div><strong>XML guardado:</strong> {{ $record->xml_path ? 'Sí' : 'No' }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Conceptos</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $record->concepts->count() }} concepto(s). Impuestos en conceptos: {{ $conceptTaxCount }}.
                    </p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Clave SAT</th>
                            <th class="px-3 py-2">No. identificación</th>
                            <th class="px-3 py-2">Descripción XML</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2">Unidad</th>
                            <th class="px-3 py-2 text-right">Valor unitario</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                            <th class="px-3 py-2 text-right">Descuento</th>
                            <th class="px-3 py-2">Impuestos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($record->concepts as $concept)
                            <tr>
                                <td class="px-3 py-2">
                                    <div>{{ $SatLabel::label('product_service', $concept->product_key) }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $concept->identification_number ?: '-' }}</td>
                                <td class="px-3 py-2">
                                    <div class="max-w-xl whitespace-normal">{{ $concept->description ?: '-' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $concept->quantity, 6) }}</td>
                                <td class="px-3 py-2">{{ $SatLabel::label('unit_code', $concept->unit_key) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) $concept->unit_price, 2) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) $concept->amount, 2) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) $concept->discount, 2) }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        $taxes = is_array($concept->taxes) ? $concept->taxes : [];
                                    @endphp

                                    @if (count($taxes) === 0)
                                        -
                                    @else
                                        <div class="space-y-1">
                                            @foreach ($taxes as $tax)
                                                <div>
                                                    {{ $SatLabel::tax($tax['tax'] ?? null) }}
                                                    {{ $SatLabel::taxDirection($tax['tax_direction'] ?? null) }}
                                                    {{ isset($tax['rate_or_fee']) ? $SatLabel::ratePercent($tax['rate_or_fee']) : '' }}
                                                    ${{ number_format((float) ($tax['amount'] ?? 0), 2) }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-6 text-center text-gray-500">
                                    Este CFDI no tiene conceptos registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Impuestos globales</h3>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Impuesto</th>
                            <th class="px-3 py-2">Factor</th>
                            <th class="px-3 py-2 text-right">Tasa / cuota</th>
                            <th class="px-3 py-2 text-right">Base</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($record->taxes as $tax)
                            <tr>
                                <td class="px-3 py-2">{{ $SatLabel::taxDirection($tax->tax_direction) }}</td>
                                <td class="px-3 py-2">{{ $SatLabel::tax($tax->tax) }}</td>
                                <td class="px-3 py-2">{{ $tax->factor_type ?: '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $SatLabel::ratePercent($tax->rate_or_fee) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) $tax->base, 2) }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format((float) $tax->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                                    Este CFDI no tiene impuestos globales registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Próximas acciones</h3>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-700">
                    Crear compra / gasto desde CFDI recibido.
                </div>
                <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-700">
                    Relacionar CFDI emitido con venta/factura Bexia.
                </div>
                <div class="rounded-lg border border-gray-100 p-4 text-sm text-gray-700">
                    Conciliar complementos de pago contra CxC/CxP.
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
