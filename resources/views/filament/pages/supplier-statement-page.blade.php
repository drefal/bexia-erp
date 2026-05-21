<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Filtros
            </x-slot>

            @php
                $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
                $exportQuery = http_build_query([
                    'supplier_key' => $this->supplierKey,
                    'date_from' => $this->dateFrom,
                    'date_to' => $this->dateTo,
                ]);
                $supplierPdfUrl = route('account-payables.reports.supplier-statement.pdf', ['tenant' => $tenantId]) . '?' . $exportQuery;
                $supplierExcelUrl = route('account-payables.reports.supplier-statement.excel', ['tenant' => $tenantId]) . '?' . $exportQuery;
            @endphp

            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ $supplierPdfUrl }}" target="_blank" style="display:inline-flex;align-items:center;justify-content:center;border-radius:0.5rem;background:#2563eb;color:#ffffff;padding:0.5rem 1rem;font-size:0.875rem;font-weight:600;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,0.08);">
                    Descargar PDF
                </a>

                <a href="{{ $supplierExcelUrl }}" style="display:inline-flex;align-items:center;justify-content:center;border-radius:0.5rem;background:#2563eb;color:#ffffff;padding:0.5rem 1rem;font-size:0.875rem;font-weight:600;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,0.08);">
                    Descargar Excel
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
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
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Desde</label>
                    <input type="date" wire:model.live="dateFrom" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Hasta</label>
                    <input type="date" wire:model.live="dateTo" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
            </div>
        </x-filament::section>

        @php($totals = $this->totals)

        <div class="grid gap-4 md:grid-cols-4">
            <x-filament::section>
                <div class="text-xs text-gray-500">Documentos</div>
                <div class="mt-1 text-lg font-semibold">{{ $this->money($totals['documents_total']) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-xs text-gray-500">Pagado en documentos</div>
                <div class="mt-1 text-lg font-semibold">{{ $this->money($totals['paid_total']) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-xs text-gray-500">Saldo pendiente</div>
                <div class="mt-1 text-lg font-semibold">{{ $this->money($totals['balance_total']) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-xs text-gray-500">Pagos aplicados periodo</div>
                <div class="mt-1 text-lg font-semibold">{{ $this->money($totals['payments_total']) }}</div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Documentos CxP</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                            <th class="px-3 py-2">Folio</th>
                            <th class="px-3 py-2">Proveedor</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Vence</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2 text-right">Total</th>
                            <th class="px-3 py-2 text-right">Pagado</th>
                            <th class="px-3 py-2 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->payables as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $row->number }}</td>
                                <td class="px-3 py-2">{{ $row->supplier_name ?: 'Proveedor sin nombre' }}</td>
                                <td class="px-3 py-2">{{ $row->issue_date ? \Carbon\Carbon::parse($row->issue_date)->format('d/m/Y') : '-' }}</td>
                                <td class="px-3 py-2">{{ $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('d/m/Y') : '-' }}</td>
                                <td class="px-3 py-2">{{ $this->statusLabel($row->status) }}</td>
                                <td class="px-3 py-2 text-right">{{ $this->money($row->total, $row->currency) }}</td>
                                <td class="px-3 py-2 text-right">{{ $this->money($row->paid_total, $row->currency) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ $this->money($row->balance_total, $row->currency) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-gray-500">
                                    Sin documentos en el periodo seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Pagos</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                            <th class="px-3 py-2">Pago</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Proveedor</th>
                            <th class="px-3 py-2">CxP</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Póliza</th>
                            <th class="px-3 py-2">Referencia</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->payments as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium">#{{ $row->id }}</td>
                                <td class="px-3 py-2">{{ $row->payment_date ? \Carbon\Carbon::parse($row->payment_date)->format('d/m/Y') : '-' }}</td>
                                <td class="px-3 py-2">{{ $row->supplier_name ?: 'Proveedor sin nombre' }}</td>
                                <td class="px-3 py-2">{{ $row->payable_number }}</td>
                                <td class="px-3 py-2">{{ $this->statusLabel($row->status) }}</td>
                                <td class="px-3 py-2">{{ $row->entry_number ?: 'Sin póliza' }}</td>
                                <td class="px-3 py-2">{{ $row->reference ?: '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $this->money($row->amount, $row->currency) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-gray-500">
                                    Sin pagos en el periodo seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
