<x-filament-panels::page>
<style>
    .bexia-accounting-page {
        width: 100%;
        max-width: none;
    }

    .bexia-accounting-card-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        width: 100%;
    }

    @media (max-width: 1280px) {
        .bexia-accounting-card-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .bexia-accounting-card-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .bexia-accounting-card-grid {
            grid-template-columns: 1fr;
        }
    }

    .bexia-accounting-card,
    .bexia-accounting-section {
        border-radius: 0.75rem;
        background: white;
        padding: 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        border: 1px solid rgba(15, 23, 42, 0.08);
    }

    .bexia-accounting-card {
        min-height: 86px;
    }

    .dark .bexia-accounting-card,
    .dark .bexia-accounting-section {
        background: rgb(17 24 39);
        border-color: rgba(255, 255, 255, 0.10);
    }

    .bexia-accounting-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
    }
</style>

    

    @php
        $stats = $this->stats();
        $statusSummary = $this->accountingStatusSummary();
        $totalsByAccount = $this->totalsByAccount();
        $totalsBySourceType = $this->totalsBySourceType();
        $valuationByProduct = $this->valuationByProduct();

        $fmt = fn ($value) => number_format((float) $value, 6);
        $money = fn ($value) => '$ ' . number_format((float) $value, 2) . ' MXN';
    @endphp

    <div class="bexia-accounting-page space-y-6">
        <div class="bexia-accounting-card-grid">
            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Asientos contables</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['entries_total'] }}</div>
            </div>

            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Partidas contables</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['entry_lines_total'] }}</div>
            </div>

            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Movimientos de inventario</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['valuation_layers_total'] }}</div>
            </div>

            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Registros de auditoría</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['posting_audits_total'] }}</div>
            </div>
        </div>

        <div class="bexia-accounting-card-grid">
            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Asientos con diferencia</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['unbalanced_entries'] }}</div>
            </div>

            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Documentos duplicados</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['duplicate_sources'] }}</div>
            </div>

            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Movimientos sin detalle</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['inventory_entries_without_layer'] }}</div>
            </div>

            <div class="bexia-accounting-card">
                <div class="text-sm text-gray-500">Productos POS sin costo</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['pos_products_without_cost'] }}</div>
            </div>
        </div>

        <div class="bexia-accounting-section">
            <h2 class="text-lg font-semibold">Estado contable de documentos</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="bexia-accounting-table text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Documento</th>
                            <th class="py-2 pr-4">Estado</th>
                            <th class="py-2 pr-4 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statusSummary as $table)
                            @foreach ($table['counts'] as $count)
                                <tr class="border-b dark:border-gray-800">
                                    <td class="py-2 pr-4 font-medium">{{ $table['table_label'] }}</td>
                                    <td class="py-2 pr-4">{{ $count['status_label'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $count['total'] }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-gray-500">Sin datos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="bexia-accounting-card">
                <h2 class="text-lg font-semibold">Saldos por cuenta</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="bexia-accounting-table text-sm">
                        <thead>
                            <tr class="border-b text-left dark:border-gray-700">
                                <th class="py-2 pr-4">Cuenta</th>
                                <th class="py-2 pr-4 text-right">Debe</th>
                                <th class="py-2 pr-4 text-right">Haber</th>
                                <th class="py-2 pr-4 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($totalsByAccount as $row)
                                <tr class="border-b dark:border-gray-800">
                                    <td class="py-2 pr-4">{{ $row->code }} {{ $row->name }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row->debit) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row->credit) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row->balance) }}</td>
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

            <div class="bexia-accounting-card">
                <h2 class="text-lg font-semibold">Totales por operación</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="bexia-accounting-table text-sm">
                        <thead>
                            <tr class="border-b text-left dark:border-gray-700">
                                <th class="py-2 pr-4">Operación</th>
                                <th class="py-2 pr-4 text-right">Asientos</th>
                                <th class="py-2 pr-4 text-right">Debe</th>
                                <th class="py-2 pr-4 text-right">Haber</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($totalsBySourceType as $row)
                                <tr class="border-b dark:border-gray-800">
                                    <td class="py-2 pr-4">{{ $row->source_type_label ?? 'Sin origen' }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $row->entries }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row->debit) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row->credit) }}</td>
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

        <div class="bexia-accounting-section">
            <h2 class="text-lg font-semibold">Existencia y valor por producto</h2>

            <div class="mt-4 overflow-x-auto">
                <table class="bexia-accounting-table text-sm">
                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="py-2 pr-4">Empresa</th>
                            <th class="py-2 pr-4">Producto</th>
                            <th class="py-2 pr-4 text-right">Cantidad neta</th>
                            <th class="py-2 pr-4 text-right">Valor neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($valuationByProduct as $row)
                            <tr class="border-b dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $row->company_id }}</td>
                                <td class="py-2 pr-4">{{ $row->product_id ?? 'Sin producto' }}</td>
                                <td class="py-2 pr-4 text-right">{{ $fmt($row->net_quantity) }}</td>
                                <td class="py-2 pr-4 text-right">{{ $money($row->net_value) }}</td>
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
