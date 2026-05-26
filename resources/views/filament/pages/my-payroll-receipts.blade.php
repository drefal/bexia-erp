<x-filament-panels::page>
    @php
        $receipts = $this->receipts();
        $summary = $this->summary();
        $latest = $summary['latest'];
    @endphp

    <div class="space-y-6">
        @if (! $employee)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <div class="text-lg font-semibold">No tienes empleado ligado</div>
                <div class="mt-1 text-sm">
                    Tu usuario debe estar relacionado con un empleado activo para consultar recibos internos.
                </div>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $employee->name }}
                        </div>
                        <div class="mt-1 text-sm text-gray-500">
                            {{ $employee->employee_number ?: 'Sin número de empleado' }}
                            @if ($employee->hrDepartment)
                                · {{ $employee->hrDepartment->name }}
                            @endif
                            @if ($employee->hrJobPosition)
                                · {{ $employee->hrJobPosition->name }}
                            @endif
                        </div>
                    </div>

                    <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        Recibos internos sin validez CFDI
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">Recibos</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $summary['count'] }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">Neto acumulado</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $this->money($summary['net_total']) }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">Bruto acumulado</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $this->money($summary['gross_total']) }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">Deducciones</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $this->money($summary['deductions_total']) }}</div>
                </div>
            </div>

            @if ($latest)
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
                    <div class="text-sm font-semibold uppercase">Último recibo</div>
                    <div class="mt-2 grid gap-3 md:grid-cols-4">
                        <div>
                            <div class="text-xs opacity-75">Periodo</div>
                            <div class="font-semibold">
                                {{ $this->dateOnly($latest->payrollRun?->period_start) }}
                                -
                                {{ $this->dateOnly($latest->payrollRun?->period_end) }}
                            </div>
                        </div>

                        <div>
                            <div class="text-xs opacity-75">Fecha de pago</div>
                            <div class="font-semibold">{{ $this->dateOnly($latest->payrollRun?->payment_date) }}</div>
                        </div>

                        <div>
                            <div class="text-xs opacity-75">Estado</div>
                            <div class="font-semibold">{{ $this->statusLabel($latest->payrollRun?->status) }}</div>
                        </div>

                        <div>
                            <div class="text-xs opacity-75">Neto</div>
                            <div class="font-semibold">{{ $this->money($latest->net_amount) }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="text-lg font-semibold">Historial de recibos</div>
                    <div class="mt-1 text-sm text-gray-500">
                        Solo se muestran recibos de pre-nóminas calculadas, aprobadas o cerradas.
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Pre-nómina</th>
                                <th class="px-4 py-3 text-left font-semibold">Periodo</th>
                                <th class="px-4 py-3 text-left font-semibold">Pago</th>
                                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                                <th class="px-4 py-3 text-right font-semibold">Bruto</th>
                                <th class="px-4 py-3 text-right font-semibold">Deducciones</th>
                                <th class="px-4 py-3 text-right font-semibold">Neto</th>
                                <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($receipts as $receipt)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $receipt->payrollRun?->name ?: 'Pre-nómina' }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $this->periodTypeLabel($receipt->payrollRun?->period_type) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $this->dateOnly($receipt->payrollRun?->period_start) }}
                                        -
                                        {{ $this->dateOnly($receipt->payrollRun?->period_end) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $this->dateOnly($receipt->payrollRun?->payment_date) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                            {{ $this->statusLabel($receipt->payrollRun?->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ $this->money($receipt->gross_amount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ $this->money($receipt->deductions_amount) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $this->money($receipt->net_amount) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            wire:click="downloadReceipt({{ $receipt->id }})"
                                            wire:loading.attr="disabled"
                                            class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                        >
                                            Descargar PDF
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                        Todavía no tienes recibos internos disponibles.
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
