<x-filament-panels::page>
    <style>
        .bexia-report-page {
            width: 100%;
            max-width: none;
        }

        .bexia-report-card-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            width: 100%;
        }

        @media (max-width: 1280px) {
            .bexia-report-card-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .bexia-report-card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .bexia-report-card-grid {
                grid-template-columns: 1fr;
            }
        }

        .bexia-report-card,
        .bexia-report-section {
            border-radius: 0.75rem;
            background: white;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .dark .bexia-report-card,
        .dark .bexia-report-section {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, 0.10);
        }

        .bexia-report-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }
    </style>

    @php
        $report = $this->report();
        $summary = $report['summary'] ?? [];
        $alerts = $report['alerts'] ?? [];

        $money = fn ($value) => '$ ' . number_format((float) $value, 2) . ' MXN';
        $qty = fn ($value) => number_format((float) $value, 6);

        $cards = [
            ['label' => 'Asientos', 'value' => $summary['entries'] ?? 0],
            ['label' => 'Partidas', 'value' => $summary['lines'] ?? 0],
            ['label' => 'Movimientos inventario', 'value' => $summary['valuation_layers'] ?? 0],
            ['label' => 'Auditorías', 'value' => $summary['audits'] ?? 0],
        ];

        $alertCards = [
            ['label' => 'Asientos con diferencia', 'value' => $alerts['unbalanced_entries'] ?? 0],
            ['label' => 'Asientos sin partidas', 'value' => $alerts['entries_without_lines'] ?? 0],
            ['label' => 'Movimientos sin detalle', 'value' => $alerts['inventory_entries_without_layer'] ?? 0],
            ['label' => 'Fuentes duplicadas', 'value' => $alerts['duplicate_sources'] ?? 0],
            ['label' => 'Productos POS sin costo', 'value' => $alerts['pos_products_without_cost'] ?? 0],
        ];
    @endphp

    <div class="bexia-report-page space-y-6">
        {{-- V5_52_4T_EXPORT_BUTTONS --}}
        <div class="bexia-report-section">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Exportaciones</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Descarga los reportes contables en CSV para revisión en Excel.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        wire:click="exportTrialBalance"
                    >
                        Balanza CSV
                    </x-filament::button>

                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        wire:click="exportSourceTotals"
                    >
                        Operaciones CSV
                    </x-filament::button>

                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        wire:click="exportLedger"
                    >
                        Mayor CSV
                    </x-filament::button>

                    <x-filament::button
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        wire:click="exportInventoryValuation"
                    >
                        Inventario CSV
                    </x-filament::button>
                </div>
            </div>
        </div>

        <div class="bexia-report-card-grid">
            @foreach ($cards as $card)
                <div class="bexia-report-card">
                    <div class="text-sm text-gray-500">{{ $card['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="bexia-report-card-grid">
            @foreach ($alertCards as $card)
                <div class="bexia-report-card">
                    <div class="text-sm text-gray-500">{{ $card['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="bexia-report-section">
            <h2 class="text-lg font-semibold">Balanza de comprobación</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="bexia-report-table text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Cuenta</th>
                            <th class="py-2 pr-4 text-right">Movimientos</th>
                            <th class="py-2 pr-4 text-right">Debe</th>
                            <th class="py-2 pr-4 text-right">Haber</th>
                            <th class="py-2 pr-4 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($report['trial_balance'] ?? []) as $row)
                            <tr class="border-b dark:border-gray-800">
                                <td class="py-2 pr-4">
                                    {{ $row['code'] ?? '' }} {{ $row['name'] ?? '' }}
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $row['line_count'] ?? 0 }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['debit'] ?? 0) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['credit'] ?? 0) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['balance'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-gray-500">Sin datos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bexia-report-section">
            <h2 class="text-lg font-semibold">Totales por operación</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="bexia-report-table text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Operación</th>
                            <th class="py-2 pr-4 text-right">Asientos</th>
                            <th class="py-2 pr-4 text-right">Debe</th>
                            <th class="py-2 pr-4 text-right">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($report['source_totals'] ?? []) as $row)
                            <tr class="border-b dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $row['source_label'] ?? 'Sin origen' }}</td>
                                <td class="py-2 pr-4 text-right">{{ $row['entries'] ?? 0 }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['debit'] ?? 0) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['credit'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-gray-500">Sin datos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bexia-report-section">
            <h2 class="text-lg font-semibold">Mayor auxiliar reciente</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="bexia-report-table text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Asiento</th>
                            <th class="py-2 pr-4">Cuenta</th>
                            <th class="py-2 pr-4">Concepto</th>
                            <th class="py-2 pr-4 text-right">Debe</th>
                            <th class="py-2 pr-4 text-right">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($report['ledger'] ?? []) as $row)
                            <tr class="border-b dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $row['entry_date'] ?? '' }}</td>
                                <td class="py-2 pr-4">{{ $row['entry_number'] ?? '' }}</td>
                                <td class="py-2 pr-4">{{ $row['account_code'] ?? '' }} {{ $row['account_name'] ?? '' }}</td>
                                <td class="py-2 pr-4">{{ $row['label'] ?? '' }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['debit'] ?? 0) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['credit'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-gray-500">Sin datos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bexia-report-section">
            <h2 class="text-lg font-semibold">Inventario contable</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="bexia-report-table text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Producto</th>
                            <th class="py-2 pr-4 text-right">Capas</th>
                            <th class="py-2 pr-4 text-right">Cantidad neta</th>
                            <th class="py-2 pr-4 text-right">Valor neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($report['inventory_valuation'] ?? []) as $row)
                            <tr class="border-b dark:border-gray-800">
                                <td class="py-2 pr-4">
                                    @if (! empty($row['product_id']))
                                        #{{ $row['product_id'] }}
                                    @else
                                        Sin producto
                                    @endif

                                    {{ $row['product_reference'] ?? '' }}
                                    {{ $row['product_name'] ?? '' }}
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $row['layers'] ?? 0 }}</td>
                                <td class="py-2 pr-4 text-right">{{ $qty($row['net_quantity'] ?? 0) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row['net_value'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-gray-500">Sin datos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
