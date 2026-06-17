<x-filament-panels::page>
    @php
        $summary = data_get($report, 'summary', []);
        $resico = data_get($report, 'calculation.resico', []);
        $iva = data_get($report, 'calculation.iva', []);
        $details = data_get($report, 'details', []);
        $warnings = data_get($report, 'warnings', []);

        $money = fn ($value) => '$' . number_format((float) ($value ?? 0), 2);
        $percent = fn ($value) => $value === null ? 'N/A' : number_format(((float) $value) * 100, 2) . '%';
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        Estimación fiscal RESICO
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Cálculo preliminar basado en XML emitidos y recibidos del periodo seleccionado.
                    </p>
                </div>

                <div class="w-full md:w-64">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Periodo</label>
                    <select
                        wire:model.live="period"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        @forelse ($periodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @empty
                            <option value="">Sin XML procesados</option>
                        @endforelse
                    </select>
                </div>
            </div>
        </div>

        @if (empty($report))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                No hay información XML procesada para calcular RESICO.
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">ISR RESICO estimado</div>
                    <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $money(data_get($resico, 'isr_estimado_a_pagar')) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Base {{ $money(data_get($resico, 'base_isr_resico_estimado')) }} · Tasa {{ $percent(data_get($resico, 'tasa_resico_mensual')) }}
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">IVA estimado</div>
                    <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $money(data_get($iva, 'iva_estimado_a_pagar')) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Trasladado - acreditable - retenido
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">XML considerados</div>
                    <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ number_format((int) data_get($summary, 'docs_vigentes', 0)) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Cancelados excluidos: {{ number_format((int) data_get($summary, 'docs_cancelados_excluidos', 0)) }}
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Recibidos PPD</div>
                    <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $money(data_get($summary, 'received_ppd_subtotal')) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Requiere complemento de pago.
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">ISR RESICO</h3>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Ingresos emitidos base</dt>
                            <dd class="font-medium">{{ $money(data_get($resico, 'base_isr_resico_estimado')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Tasa mensual RESICO</dt>
                            <dd class="font-medium">{{ $percent(data_get($resico, 'tasa_resico_mensual')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">ISR causado</dt>
                            <dd class="font-medium">{{ $money(data_get($resico, 'isr_causado_estimado')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">ISR retenido detectado</dt>
                            <dd class="font-medium">{{ $money(data_get($resico, 'isr_retenido_detectado_emitidos')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t pt-3 dark:border-gray-700">
                            <dt class="font-semibold text-gray-700 dark:text-gray-200">ISR estimado a pagar</dt>
                            <dd class="font-bold">{{ $money(data_get($resico, 'isr_estimado_a_pagar')) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">IVA</h3>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IVA trasladado emitido</dt>
                            <dd class="font-medium">{{ $money(data_get($iva, 'iva_trasladado_emitido_neto')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IVA acreditable recibido</dt>
                            <dd class="font-medium">{{ $money(data_get($iva, 'iva_acreditable_recibido_neto')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Diferencia antes retenciones</dt>
                            <dd class="font-medium">{{ $money(data_get($iva, 'iva_diferencia_antes_retenciones')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IVA retenido por clientes</dt>
                            <dd class="font-medium">{{ $money(data_get($iva, 'iva_retenido_por_clientes_detectado_emitidos')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t pt-3 dark:border-gray-700">
                            <dt class="font-semibold text-gray-700 dark:text-gray-200">IVA estimado a pagar</dt>
                            <dd class="font-bold">{{ $money(data_get($iva, 'iva_estimado_a_pagar')) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Base de XML</h3>

                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <div>
                        <div class="text-xs text-gray-500">Emitidos ingreso</div>
                        <div class="text-lg font-semibold">{{ number_format((int) data_get($summary, 'issued_income_count', 0)) }}</div>
                        <div class="text-xs text-gray-500">{{ $money(data_get($summary, 'issued_income_subtotal')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Recibidos ingreso</div>
                        <div class="text-lg font-semibold">{{ number_format((int) data_get($summary, 'received_income_count', 0)) }}</div>
                        <div class="text-xs text-gray-500">{{ $money(data_get($summary, 'received_income_subtotal')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Emitidos PPD</div>
                        <div class="text-lg font-semibold">{{ $money(data_get($summary, 'issued_ppd_subtotal')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Recibidos PPD</div>
                        <div class="text-lg font-semibold">{{ $money(data_get($summary, 'received_ppd_subtotal')) }}</div>
                    </div>
                </div>
            </div>

            @if (! empty($warnings))
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                    <h3 class="font-semibold">Advertencias de cálculo</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        CFDI considerados
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Primeros 100 CFDI con XML del periodo.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                <th class="px-4 py-3 text-left font-medium">Tipo</th>
                                <th class="px-4 py-3 text-left font-medium">Flujo</th>
                                <th class="px-4 py-3 text-left font-medium">RFC emisor</th>
                                <th class="px-4 py-3 text-left font-medium">RFC receptor</th>
                                <th class="px-4 py-3 text-right font-medium">Subtotal</th>
                                <th class="px-4 py-3 text-right font-medium">IVA</th>
                                <th class="px-4 py-3 text-right font-medium">ISR ret.</th>
                                <th class="px-4 py-3 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse (array_slice($details, 0, 100) as $row)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'issued_at') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'cfdi_type') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'direction') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'issuer_rfc') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'receiver_rfc') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'subtotal')) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'iva_transferred')) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'isr_withheld')) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'total')) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-6 text-center text-gray-500">
                                        No hay CFDI con XML para este periodo.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
