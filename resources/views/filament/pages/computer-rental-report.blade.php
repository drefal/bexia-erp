<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $summary = $this->summary();
        $stationSummary = $this->stationSummary();
    @endphp

    <div class="space-y-6">

        {{-- CIBER3R3_REPORT_FILTERS --}}
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm
                   dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <div>
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        Desde
                    </label>

                    <input
                        type="date"
                        wire:model.live="from"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm
                               dark:border-gray-700 dark:bg-gray-800"
                    >
                </div>

                <div>
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        Hasta
                    </label>

                    <input
                        type="date"
                        wire:model.live="to"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm
                               dark:border-gray-700 dark:bg-gray-800"
                    >
                </div>

                <div>
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        PC
                    </label>

                    <select
                        wire:model.live="station_id"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm
                               dark:border-gray-700 dark:bg-gray-800"
                    >
                        <option value="">Todas</option>

                        @foreach ($this->stationOptions() as $id => $label)
                            <option value="{{ $id }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        Estado
                    </label>

                    <select
                        wire:model.live="status"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm
                               dark:border-gray-700 dark:bg-gray-800"
                    >
                        <option value="">Todos</option>

                        @foreach ($this->statusOptions() as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        Tipo de tarifa
                    </label>

                    <select
                        wire:model.live="billing_mode"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm
                               dark:border-gray-700 dark:bg-gray-800"
                    >
                        <option value="">Todos</option>

                        @foreach ($this->billingModeOptions() as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        Usuario
                    </label>

                    <select
                        wire:model.live="user_id"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm
                               dark:border-gray-700 dark:bg-gray-800"
                    >
                        <option value="">Todos</option>

                        @foreach ($this->userOptions() as $id => $name)
                            <option value="{{ $id }}">
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium
                           text-gray-700 hover:bg-gray-50
                           dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Limpiar filtros
                </button>
            </div>
        </div>

        {{-- CIBER3R3_REPORT_KPIS --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Sesiones</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['sessions'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Pagadas</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['paid'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Canceladas</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['cancelled'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Tiempo ocupado</div>
                <div class="mt-1 text-2xl font-semibold">
                    {{ number_format($summary['real_hours'], 2) }} h
                </div>
                <div class="text-xs text-gray-500">Tiempo real</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Facturable</div>
                <div class="mt-1 text-2xl font-semibold">
                    {{ number_format($summary['billable_hours'], 2) }} h
                </div>
                <div class="text-xs text-gray-500">
                    {{ $summary['billable_minutes'] }} min
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Ingresos renta</div>
                <div class="mt-1 text-2xl font-semibold">
                    ${{ number_format($summary['rental_revenue'], 2) }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Consumos</div>
                <div class="mt-1 text-2xl font-semibold">
                    ${{ number_format($summary['consumption_revenue'], 2) }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Renta + consumos</div>
                <div class="mt-1 text-2xl font-semibold">
                    ${{ number_format($summary['rental_plus_consumption'], 2) }}
                </div>
                <div class="text-xs text-gray-500">
                    PDV vinculado:
                    ${{ number_format($summary['linked_pos_total'], 2) }}
                </div>
            </div>
        </div>

        {{-- CIBER3R3_STATION_SUMMARY --}}
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm
                   dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="text-lg font-semibold">
                    Operación por PC
                </div>

                <div class="mt-1 text-sm text-gray-500">
                    La participación corresponde al tiempo ocupado dentro del periodo,
                    no a un porcentaje de capacidad disponible.
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">PC</th>
                            <th class="px-4 py-3 text-right font-semibold">Sesiones</th>
                            <th class="px-4 py-3 text-right font-semibold">Pagadas</th>
                            <th class="px-4 py-3 text-right font-semibold">Canceladas</th>
                            <th class="px-4 py-3 text-right font-semibold">Horas ocupadas</th>
                            <th class="px-4 py-3 text-right font-semibold">Horas facturables</th>
                            <th class="px-4 py-3 text-right font-semibold">Participación</th>
                            <th class="px-4 py-3 text-right font-semibold">Renta</th>
                            <th class="px-4 py-3 text-right font-semibold">Consumos</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($stationSummary as $station)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold">
                                        {{ $station['station_code'] ?: 'PC' }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $station['station_name'] }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right">{{ $station['sessions'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $station['paid'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $station['cancelled'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($station['real_hours'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($station['billable_hours'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($station['share_percent'], 1) }}%</td>
                                <td class="px-4 py-3 text-right">${{ number_format($station['rental_revenue'], 2) }}</td>
                                <td class="px-4 py-3 text-right">${{ number_format($station['consumption_revenue'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                    No hay operación para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- CIBER3R3_DETAIL --}}
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm
                   dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="text-lg font-semibold">
                    Detalle de sesiones
                </div>

                <div class="mt-1 text-sm text-gray-500">
                    Periodo {{ $from }} a {{ $to }}
                    · {{ $rows->count() }} registros
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">PC</th>
                            <th class="px-4 py-3 text-left font-semibold">Inicio / fin</th>
                            <th class="px-4 py-3 text-left font-semibold">Usuario</th>
                            <th class="px-4 py-3 text-left font-semibold">Tarifa</th>
                            <th class="px-4 py-3 text-right font-semibold">Tiempo real</th>
                            <th class="px-4 py-3 text-right font-semibold">Facturable</th>
                            <th class="px-4 py-3 text-right font-semibold">Renta</th>
                            <th class="px-4 py-3 text-right font-semibold">Consumos</th>
                            <th class="px-4 py-3 text-left font-semibold">Ticket</th>
                            <th class="px-4 py-3 text-right font-semibold">Total ticket</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado</th>
                            <th class="px-4 py-3 text-left font-semibold">Cancelación</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-semibold">
                                        {{ $row->station_code ?: '—' }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $row->station_name ?: '' }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div>{{ $this->dateTime($row->started_at) }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $this->dateTime($row->ended_at) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div>
                                        {{ $row->opened_by_name ?: '—' }}
                                    </div>

                                    @if (
                                        $row->closed_by_name
                                        && $row->closed_by_name !== $row->opened_by_name
                                    )
                                        <div class="text-xs text-gray-500">
                                            Cierre: {{ $row->closed_by_name }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div>
                                        {{ $row->rate_name ?: '—' }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $this->billingModeLabel($row->billing_mode) }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    {{ $this->duration((int) $row->duration_seconds) }}
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    {{ (int) $row->billable_minutes }} min
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    ${{ number_format((float) $row->rental_amount, 2) }}
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    ${{ number_format((float) $row->consumption_total, 2) }}

                                    @if ((int) $row->consumption_lines > 0)
                                        <div class="text-xs text-gray-500">
                                            {{ (int) $row->consumption_lines }}
                                            {{ (int) $row->consumption_lines === 1 ? 'línea' : 'líneas' }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($row->pos_number)
                                        <div class="font-medium">
                                            {{ $row->pos_number }}
                                        </div>

                                        <div class="text-xs text-gray-500">
                                            {{ $this->statusLabel($row->pos_status) }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($row->pos_order_id)
                                        ${{ number_format((float) $row->pos_total, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $status = (string) $row->rental_status;
                                    @endphp

                                    <span
                                        class="inline-flex rounded-lg px-2 py-1 text-xs font-semibold
                                            {{ $status === 'paid'
                                                ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
                                                : ($status === 'cancelled'
                                                    ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300'
                                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300') }}"
                                    >
                                        {{ $this->statusLabel($status) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 min-w-48">
                                    @if ($row->cancelled_at)
                                        <div>
                                            {{ $this->dateTime($row->cancelled_at) }}
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $row->cancel_reason ?: 'Sin motivo registrado' }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-gray-500">
                                    No hay sesiones para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
