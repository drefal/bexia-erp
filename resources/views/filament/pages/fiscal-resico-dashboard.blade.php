<x-filament-panels::page>
    @php
        $summary = data_get($report, 'summary', []);
        $resico = data_get($report, 'calculation.resico', []);
        $iva = data_get($report, 'calculation.iva', []);
        $details = data_get($report, 'details', []);
        $ppdDetails = data_get($report, 'ppd_details', []);
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
                        Cálculo preliminar basado en XML. Separa IVA total XML de IVA acreditable tipo declaración.
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

                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-800 dark:bg-emerald-950">
                    <div class="text-sm text-emerald-700 dark:text-emerald-200">IVA estimado tipo declaración</div>
                    <div class="mt-2 text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                        {{ $money(data_get($iva, 'iva_estimado_a_pagar_declaracion_like')) }}
                    </div>
                    <div class="mt-1 text-xs text-emerald-700 dark:text-emerald-200">
                        PUE + G01/G03 + forma bancarizada.
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">IVA total XML amplio</div>
                    <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $money(data_get($iva, 'iva_estimado_a_pagar')) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Toma todo el IVA recibido vigente.
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-800 dark:bg-amber-950">
                    <div class="text-sm text-amber-700 dark:text-amber-200">CFDI PPD / pagos pendientes</div>
                    <div class="mt-2 text-2xl font-bold text-amber-900 dark:text-amber-100">
                        {{ number_format(count($ppdDetails) + (int) data_get($iva, 'iva_complementos_pago_pendiente', 0)) }}
                    </div>
                    <div class="mt-1 text-xs text-amber-700 dark:text-amber-200">
                        Requieren complemento pago20.
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">IVA tipo declaración SAT</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Separa el IVA total recibido de lo que preliminarmente sería acreditable.
                </p>

                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <div>
                        <div class="text-xs text-gray-500">IVA trasladado emitido</div>
                        <div class="text-lg font-semibold">{{ $money(data_get($iva, 'iva_trasladado_emitido_neto')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">IVA acreditable PUE estimado</div>
                        <div class="text-lg font-semibold">{{ $money(data_get($iva, 'iva_acreditable_pue_g01_g03_bancarizada')) }}</div>
                        <div class="text-xs text-gray-500">
                            {{ number_format((int) data_get($summary, 'received_pue_g01_g03_banked_count', 0)) }} CFDI
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">IVA complementos pago detectado</div>
                        <div class="text-lg font-semibold">{{ $money(data_get($iva, 'iva_complementos_pago_detectado')) }}</div>
                        <div class="text-xs text-gray-500">
                            Pendiente parser pago20.
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">IVA no acreditable preliminar</div>
                        <div class="text-lg font-semibold">{{ $money(data_get($iva, 'iva_no_acreditable_preliminar')) }}</div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">ISR RESICO</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Base ISR RESICO estimada</dt>
                            <dd class="font-medium">{{ $money(data_get($resico, 'base_isr_resico_estimado')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Tasa mensual RESICO</dt>
                            <dd class="font-medium">{{ $percent(data_get($resico, 'tasa_resico_mensual')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">ISR causado estimado</dt>
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
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">IVA comparativo</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IVA trasladado en facturas emitidas</dt>
                            <dd class="font-medium">{{ $money(data_get($iva, 'iva_trasladado_emitido_neto')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IVA recibido total XML</dt>
                            <dd class="font-medium">{{ $money(data_get($iva, 'iva_acreditable_recibido_neto')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">IVA acreditable tipo declaración</dt>
                            <dd class="font-medium">{{ $money(data_get($iva, 'iva_acreditable_estimado_declaracion')) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t pt-3 dark:border-gray-700">
                            <dt class="font-semibold text-gray-700 dark:text-gray-200">IVA estimado tipo declaración</dt>
                            <dd class="font-bold">{{ $money(data_get($iva, 'iva_estimado_a_pagar_declaracion_like')) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Base XML del periodo</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <div>
                        <div class="text-xs text-gray-500">Facturas emitidas</div>
                        <div class="text-lg font-semibold">{{ number_format((int) data_get($summary, 'issued_income_count', 0)) }}</div>
                        <div class="text-xs text-gray-500">{{ $money(data_get($summary, 'issued_income_subtotal')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Facturas recibidas</div>
                        <div class="text-lg font-semibold">{{ number_format((int) data_get($summary, 'received_income_count', 0)) }}</div>
                        <div class="text-xs text-gray-500">{{ $money(data_get($summary, 'received_income_subtotal')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Recibidas acreditables PUE</div>
                        <div class="text-lg font-semibold">{{ number_format((int) data_get($summary, 'received_pue_g01_g03_banked_count', 0)) }}</div>
                        <div class="text-xs text-gray-500">{{ $money(data_get($summary, 'received_pue_g01_g03_banked_subtotal')) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Recibidas excluidas preliminar</div>
                        <div class="text-lg font-semibold">{{ number_format((int) data_get($summary, 'received_excluded_from_accreditable_count', 0)) }}</div>
                        <div class="text-xs text-gray-500">{{ $money(data_get($summary, 'received_excluded_from_accreditable_iva')) }} IVA</div>
                    </div>
                </div>
            </div>

            @if (! empty($ppdDetails))
                <div class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm dark:border-amber-800 dark:bg-gray-900">
                    <div class="border-b border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950">
                        <h3 class="text-base font-semibold text-amber-900 dark:text-amber-100">
                            CFDI PPD pendientes de validar con complemento de pago
                        </h3>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-200">
                            Estas facturas están en método PPD. Para cálculo final de IVA/RESICO se debe relacionar el complemento de pago correspondiente.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">Fecha</th>
                                    <th class="px-4 py-3 text-left font-medium">Flujo</th>
                                    <th class="px-4 py-3 text-left font-medium">UUID</th>
                                    <th class="px-4 py-3 text-left font-medium">RFC emisor</th>
                                    <th class="px-4 py-3 text-right font-medium">Subtotal</th>
                                    <th class="px-4 py-3 text-right font-medium">IVA</th>
                                    <th class="px-4 py-3 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($ppdDetails as $row)
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'issued_at') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'direction_label', data_get($row, 'direction')) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ data_get($row, 'uuid') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'issuer_rfc') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'subtotal')) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'iva_transferred')) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'total')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

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
                                <th class="px-4 py-3 text-left font-medium">Flujo</th>
                                <th class="px-4 py-3 text-left font-medium">Tipo</th>
                                <th class="px-4 py-3 text-left font-medium">Método</th>
                                <th class="px-4 py-3 text-left font-medium">Forma</th>
                                <th class="px-4 py-3 text-left font-medium">Uso</th>
                                <th class="px-4 py-3 text-right font-medium">Subtotal</th>
                                <th class="px-4 py-3 text-right font-medium">IVA</th>
                                <th class="px-4 py-3 text-left font-medium">Criterio IVA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse (array_slice($details, 0, 100) as $row)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'issued_at') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'direction_label', data_get($row, 'direction')) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'cfdi_type_label', data_get($row, 'cfdi_type')) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'payment_method') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'payment_form') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ data_get($row, 'usage_cfdi') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'subtotal')) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $money(data_get($row, 'iva_transferred')) }}</td>
                                    <td class="px-4 py-3">
                                        @if (data_get($row, 'iva_acreditable_estimado'))
                                            Acreditable preliminar
                                        @else
                                            {{ data_get($row, 'iva_exclusion_reason') }}
                                        @endif
                                    </td>
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
