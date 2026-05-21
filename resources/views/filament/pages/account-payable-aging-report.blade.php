<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Filtros
            </x-slot>

            @php
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                $exportQuery = http_build_query([
                    'as_of_date' => $this->asOfDate,
                    'supplier_key' => $this->supplierKey,
                    'document_search' => $this->documentSearch,
                ]);
                $agingPdfUrl = route('account-payables.reports.aging.pdf', ['tenant' => $tenantId]) . '?' . $exportQuery;
                $agingExcelUrl = route('account-payables.reports.aging.excel', ['tenant' => $tenantId]) . '?' . $exportQuery;
            @endphp

            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ $agingPdfUrl }}" target="_blank" style="display:inline-flex;align-items:center;justify-content:center;border-radius:0.5rem;background:#2563eb;color:#ffffff;padding:0.5rem 1rem;font-size:0.875rem;font-weight:600;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,0.08);">
                    Descargar PDF
                </a>

                <a href="{{ $agingExcelUrl }}" style="display:inline-flex;align-items:center;justify-content:center;border-radius:0.5rem;background:#2563eb;color:#ffffff;padding:0.5rem 1rem;font-size:0.875rem;font-weight:600;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,0.08);">
                    Descargar Excel
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Fecha de corte</label>
                    <input type="date" wire:model.live="asOfDate" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                </div>

                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Proveedor</label>
                    <select wire:model.live="supplierKey" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">Todos los proveedores</option>
                        @foreach ($this->supplierOptions as $supplier)
                            <option value="{{ $supplier->supplier_key }}">{{ $supplier->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Folio / referencia</label>
                    <input type="text" wire:model.live.debounce.400ms="documentSearch" placeholder="Buscar CxP" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            @php($summary = $this->summary)

            <x-filament::section><div class="text-xs text-gray-500">Total saldo</div><div class="mt-1 text-lg font-semibold">{{ $this->money($summary['total']) }}</div></x-filament::section>
            <x-filament::section><div class="text-xs text-gray-500">Por vencer</div><div class="mt-1 text-lg font-semibold">{{ $this->money($summary['not_due']) }}</div></x-filament::section>
            <x-filament::section><div class="text-xs text-gray-500">1 a 30 días</div><div class="mt-1 text-lg font-semibold">{{ $this->money($summary['days_1_30']) }}</div></x-filament::section>
            <x-filament::section><div class="text-xs text-gray-500">31 a 60 días</div><div class="mt-1 text-lg font-semibold">{{ $this->money($summary['days_31_60']) }}</div></x-filament::section>
            <x-filament::section><div class="text-xs text-gray-500">61 a 90 días</div><div class="mt-1 text-lg font-semibold">{{ $this->money($summary['days_61_90']) }}</div></x-filament::section>
            <x-filament::section><div class="text-xs text-gray-500">Más de 90 días</div><div class="mt-1 text-lg font-semibold">{{ $this->money($summary['days_90_plus']) }}</div></x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Cuentas por pagar abiertas</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                            <th class="px-3 py-2">Folio</th>
                            <th class="px-3 py-2">Proveedor</th>
                            <th class="px-3 py-2">Vence</th>
                            <th class="px-3 py-2 text-right">Días vencido</th>
                            <th class="px-3 py-2">Rango</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2 text-right">Pagado</th>
                            <th class="px-3 py-2 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->rows as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $row->number }}</td>
                                <td class="px-3 py-2">{{ $row->supplier_name ?: 'Proveedor sin nombre' }}</td>
                                <td class="px-3 py-2">{{ $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('d/m/Y') : 'Sin vencimiento' }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->days_overdue }}</td>
                                <td class="px-3 py-2">{{ $row->bucket_label }}</td>
                                <td class="px-3 py-2 text-right">{{ $this->money($row->total, $row->currency) }}</td>
                                <td class="px-3 py-2 text-right">{{ $this->money($row->paid_total, $row->currency) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ $this->money($row->balance_total, $row->currency) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-gray-500">No hay cuentas por pagar abiertas con los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
