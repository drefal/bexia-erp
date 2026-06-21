<x-filament-panels::page>
    @php
        $data = $this->dashboardData();
    @endphp

    <style>
        .bexia-service-dashboard-triple-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            align-items: stretch;
        }

        .bexia-service-dashboard-triple-grid > div {
            min-width: 0;
            height: 100%;
        }

        .bexia-service-dashboard-triple-grid .space-y-3 > div {
            width: 100%;
        }

        @media (max-width: 1279px) {
            .bexia-service-dashboard-triple-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($data['cards'] as $card)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                    <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $card['value'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $card['hint'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="bexia-service-dashboard-triple-grid">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">SLA</h2>
                    <span class="text-xs text-gray-500">Semáforo por fecha prometida</span>
                </div>

                <div class="space-y-3">
                    @foreach($data['sla'] as $key => $count)
                        <div class="flex items-center justify-between gap-3">
                            <span class="{{ $this->badgeClass($key) }}">{{ $this->slaLabel($key) }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Etapas operativas</h2>
                    <p class="text-xs text-gray-500">Distribución por workflow_stage</p>
                </div>

                <div class="space-y-3">
                    @forelse($data['workflow'] as $stage => $count)
                        <div class="flex items-center justify-between gap-3">
                            <span class="{{ $this->badgeClass($stage) }}">{{ $this->stageLabel($stage) }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $count }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Sin reparaciones.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Tiempos promedio</h2>
                    <p class="text-xs text-gray-500">Horas desde recepción</p>
                </div>

                <dl class="space-y-3">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500">A inicio</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format((float) ($data['time']['avg_hours_to_start'] ?? 0), 2) }} h</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500">A reparación finalizada</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format((float) ($data['time']['avg_hours_to_finished'] ?? 0), 2) }} h</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500">A entrega</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format((float) ($data['time']['avg_hours_to_delivered'] ?? 0), 2) }} h</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-gray-500">Horas reales mano de obra</dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format((float) ($data['time']['avg_actual_labor_hours'] ?? 0), 2) }} h</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Presupuestos</h2>
                    <p class="text-xs text-gray-500">Estatus de autorización</p>
                </div>

                <div class="space-y-3">
                    @forelse($data['quotes'] as $status => $count)
                        <div class="flex items-center justify-between gap-3">
                            <span class="{{ $this->badgeClass($status) }}">{{ $this->quoteLabel($status) }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $count }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Sin presupuestos.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Cierre económico</h2>
                    <p class="text-xs text-gray-500">Importes y cobro</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="text-xs text-gray-500">Presupuestado</div>
                        <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->money($data['economic']['totals']['quote_total'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="text-xs text-gray-500">Total económico</div>
                        <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->money($data['economic']['totals']['economic_total'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="text-xs text-gray-500">Venta refacciones</div>
                        <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->money($data['economic']['totals']['parts_sale_total'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="text-xs text-gray-500">Venta mano de obra</div>
                        <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->money($data['economic']['totals']['labor_sale_total'] ?? 0) }}</div>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($data['economic']['counts'] as $status => $count)
                        <div class="flex items-center justify-between gap-3">
                            <span class="{{ $this->badgeClass($status) }}">{{ $this->economicLabel($status) }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $count }}</span>
                        </div>
                    @endforeach

                    @foreach($data['economic']['payments'] as $status => $count)
                        <div class="flex items-center justify-between gap-3">
                            <span class="{{ $this->badgeClass($status) }}">Pago: {{ $this->paymentLabel($status) }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Técnicos</h2>
                <p class="text-xs text-gray-500">Carga de trabajo e importe por técnico</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-4">Técnico</th>
                            <th class="py-2 pr-4 text-right">Total</th>
                            <th class="py-2 pr-4 text-right">Abiertas</th>
                            <th class="py-2 pr-4 text-right">Entregadas</th>
                            <th class="py-2 pr-4 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($data['technicians'] as $tech)
                            <tr>
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                                    {{ $tech['name'] }}
                                    @if(!empty($tech['employee_number']))
                                        <span class="ml-1 text-xs text-gray-500">#{{ $tech['employee_number'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $tech['total'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $tech['open_total'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $tech['delivered_total'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $this->money($tech['economic_total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-sm text-gray-500">Sin técnicos asignados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Últimas reparaciones</h2>
                <p class="text-xs text-gray-500">Registros recientes del tenant actual</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-4">Folio</th>
                            <th class="py-2 pr-4">Producto</th>
                            <th class="py-2 pr-4">Etapa</th>
                            <th class="py-2 pr-4">SLA</th>
                            <th class="py-2 pr-4 text-right">Total</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($data['latest'] as $repair)
                            <tr>
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $repair['folio'] }}</td>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $repair['product'] }}</td>
                                <td class="py-2 pr-4">
                                    <span class="{{ $this->badgeClass($repair['stage']) }}">{{ $repair['stage_label'] }}</span>
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="{{ $this->badgeClass($repair['sla_key']) }}">{{ $repair['sla_label'] }}</span>
                                    <div class="mt-1 text-xs text-gray-500">{{ $repair['sla_description'] }}</div>
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $this->money($repair['total']) }}</td>
                                <td class="py-2 pr-4 text-right">
                                    <a href="{{ $repair['edit_url'] }}" class="text-sm font-medium text-primary-600 hover:text-primary-500">
                                        Abrir
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-sm text-gray-500">Sin reparaciones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
