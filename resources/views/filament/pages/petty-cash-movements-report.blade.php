<x-filament-panels::page>
    <div class="space-y-6">

        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Desde
                    </label>

                    <input
                        type="date"
                        wire:model="dateFrom"
                        class="w-full rounded-lg border-gray-300"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Hasta
                    </label>

                    <input
                        type="date"
                        wire:model="dateTo"
                        class="w-full rounded-lg border-gray-300"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Caja chica
                    </label>

                    <select
                        wire:model="fundId"
                        class="w-full rounded-lg border-gray-300"
                    >
                        <option value="">
                            Todas
                        </option>

                        @foreach ($fundOptions as $id => $label)
                            <option value="{{ $id }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Tipo
                    </label>

                    <select
                        wire:model="movementType"
                        class="w-full rounded-lg border-gray-300"
                    >
                        <option value="">
                            Todos
                        </option>

                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Origen de fondos
                    </label>

                    <select
                        wire:model="fundingSourceId"
                        class="w-full rounded-lg border-gray-300"
                    >
                        <option value="">
                            Todos
                        </option>

                        @foreach ($fundingSourceOptions as $id => $label)
                            <option value="{{ $id }}">
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <x-filament::button
                    wire:click="applyFilters"
                    icon="heroicon-o-funnel"
                >
                    Aplicar filtros
                </x-filament::button>

                <x-filament::button
                    wire:click="clearFilters"
                    color="gray"
                    icon="heroicon-o-x-mark"
                >
                    Limpiar
                </x-filament::button>
            </div>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-filament::section>
                <div class="text-sm text-gray-500">
                    Movimientos
                </div>
                <div class="mt-1 text-2xl font-semibold">
                    {{ number_format($summary['movements'] ?? 0) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">
                    Entradas
                </div>
                <div class="mt-1 text-2xl font-semibold">
                    ${{ number_format($summary['entries'] ?? 0, 2) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">
                    Salidas
                </div>
                <div class="mt-1 text-2xl font-semibold">
                    ${{ number_format($summary['exits'] ?? 0, 2) }}
                </div>
            </x-filament::section>

            @if ($fundId)
                <x-filament::section>
                    <div class="text-sm text-gray-500">
                        Saldo inicial
                    </div>
                    <div class="mt-1 text-2xl font-semibold">
                        ${{ number_format($summary['opening_balance'] ?? 0, 2) }}
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm text-gray-500">
                        Saldo final
                    </div>
                    <div class="mt-1 text-2xl font-semibold">
                        ${{ number_format($summary['closing_balance'] ?? 0, 2) }}
                    </div>
                </x-filament::section>
            @else
                <x-filament::section>
                    <div class="text-sm text-gray-500">
                        Cajas incluidas
                    </div>
                    <div class="mt-1 text-2xl font-semibold">
                        {{ number_format($summary['funds_included'] ?? 0) }}
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm text-gray-500">
                        Variación neta
                    </div>
                    <div class="mt-1 text-2xl font-semibold">
                        ${{ number_format($summary['net_change'] ?? 0, 2) }}
                    </div>
                </x-filament::section>
            @endif
        </div>

        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1800px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left">
                            <th class="p-3">Fecha</th>
                            <th class="p-3">Caja chica</th>
                            <th class="p-3">Responsable</th>
                            <th class="p-3">Tipo</th>
                            <th class="p-3">Origen de fondos</th>
                            <th class="p-3">Cuenta origen</th>
                            <th class="p-3">Referencia</th>
                            <th class="p-3">Descripción</th>
                            <th class="p-3 text-right">Entrada</th>
                            <th class="p-3 text-right">Salida</th>
                            <th class="p-3 text-right">Saldo anterior</th>
                            <th class="p-3 text-right">Saldo posterior</th>
                            <th class="p-3">Comprobación</th>
                            <th class="p-3">Mov. Tesorería</th>
                            <th class="p-3">Usuario</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-gray-100">
                                <td class="p-3">
                                    {{ $row->movement_date }}
                                </td>

                                <td class="p-3">
                                    <a
                                        href="{{ \App\Filament\Resources\PettyCashFundResource::getUrl('view', ['record' => $row->petty_cash_fund_id]) }}"
                                        class="font-medium text-primary-600 hover:underline"
                                    >
                                        {{ $row->fund_number }}
                                        ·
                                        {{ $row->fund_name }}
                                    </a>
                                </td>

                                <td class="p-3">
                                    {{ $row->employee_name ?: '—' }}
                                </td>

                                <td class="p-3">
                                    {{ $row->type_label }}
                                </td>

                                <td class="p-3">
                                    @if ($row->funding_source_name)
                                        {{ $row->funding_source_code ? $row->funding_source_code . ' · ' : '' }}
                                        {{ $row->funding_source_name }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="p-3">
                                    {{ $row->source_account_name ?: '—' }}
                                </td>

                                <td class="p-3">
                                    {{ $row->reference ?: '—' }}
                                </td>

                                <td class="p-3">
                                    {{ $row->description ?: '—' }}
                                </td>

                                <td class="p-3 text-right">
                                    @if ($row->entry_amount > 0)
                                        ${{ number_format($row->entry_amount, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="p-3 text-right">
                                    @if ($row->exit_amount > 0)
                                        ${{ number_format($row->exit_amount, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->balance_before, 2) }}
                                </td>

                                <td class="p-3 text-right font-medium">
                                    ${{ number_format((float) $row->balance_after, 2) }}
                                </td>

                                <td class="p-3">
                                    @if ($row->expense_report_id)
                                        <a
                                            href="{{ \App\Filament\Resources\ExpenseReportResource::getUrl('view', ['record' => $row->expense_report_id]) }}"
                                            class="text-primary-600 hover:underline"
                                        >
                                            {{ $row->expense_report_number ?: ('#' . $row->expense_report_id) }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="p-3">
                                    {{ $row->treasury_movement_id ?: '—' }}
                                </td>

                                <td class="p-3">
                                    {{ $row->created_by_name ?: '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="15"
                                    class="p-8 text-center text-gray-500"
                                >
                                    No hay movimientos para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
