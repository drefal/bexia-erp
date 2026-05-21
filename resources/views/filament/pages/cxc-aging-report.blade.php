<x-filament-panels::page>
    @php
        $tenantId = $this->tenantId();
        $asOfDate = request('as_of_date', $this->defaultAsOfDate());
        $customerContactId = request('customer_contact_id');
        $documentSearch = request('document_search');
        $rows = $this->rows();
        $summary = $this->summary();
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4 font-semibold text-gray-900">Filtros</div>

            <div class="space-y-5 p-6">
                <div class="flex flex-wrap gap-3" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <a
                        href="{{ route('account-receivables.reports.aging.pdf', [
                            'tenant' => $tenantId,
                            'as_of_date' => $asOfDate,
                            'customer_contact_id' => $customerContactId,
                            'document_search' => $documentSearch,
                        ]) }}"
                        target="_blank"
                        style="display:inline-flex;align-items:center;justify-content:center;padding:9px 14px;border-radius:8px;background:#2563eb;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.08);"
                    >
                        Descargar PDF
                    </a>

                    <a
                        href="{{ route('account-receivables.reports.aging.excel', [
                            'tenant' => $tenantId,
                            'as_of_date' => $asOfDate,
                            'customer_contact_id' => $customerContactId,
                            'document_search' => $documentSearch,
                        ]) }}"
                        target="_blank"
                        style="display:inline-flex;align-items:center;justify-content:center;padding:9px 14px;border-radius:8px;background:#2563eb;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.08);"
                    >
                        Descargar Excel
                    </a>
                </div>

                <form method="GET" class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Fecha de corte</label>
                        <input
                            type="date"
                            name="as_of_date"
                            value="{{ $asOfDate }}"
                            class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 shadow-sm"
                        >
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Cliente</label>
                        <select
                            name="customer_contact_id"
                            class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 shadow-sm"
                        >
                            <option value="">Todos los clientes</option>
                            @foreach($this->customerOptions() as $id => $label)
                                <option value="{{ $id }}" @selected((string) $customerContactId === (string) $id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Folio / referencia</label>
                        <input
                            type="text"
                            name="document_search"
                            value="{{ $documentSearch }}"
                            placeholder="Buscar CxC"
                            class="mt-1 block w-full rounded-lg border-gray-300 px-3 py-2 shadow-sm"
                        >
                    </div>

                    <div class="md:col-span-3 flex flex-wrap gap-3 pt-1">
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm"
                        >
                            Aplicar filtros
                        </button>

                        <a
                            href="{{ request()->url() }}"
                            class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm"
                        >
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="text-xs text-gray-500">Total saldo</div>
                <div class="mt-2 text-lg font-bold">${{ number_format($summary['total'], 2) }} MXN</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="text-xs text-gray-500">Por vencer</div>
                <div class="mt-2 text-lg font-bold">${{ number_format($summary['not_due'], 2) }} MXN</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="text-xs text-gray-500">1 a 30 días</div>
                <div class="mt-2 text-lg font-bold">${{ number_format($summary['days_1_30'], 2) }} MXN</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="text-xs text-gray-500">31 a 60 días</div>
                <div class="mt-2 text-lg font-bold">${{ number_format($summary['days_31_60'], 2) }} MXN</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="text-xs text-gray-500">61 a 90 días</div>
                <div class="mt-2 text-lg font-bold">${{ number_format($summary['days_61_90'], 2) }} MXN</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="text-xs text-gray-500">Más de 90 días</div>
                <div class="mt-2 text-lg font-bold">${{ number_format($summary['days_90_plus'], 2) }} MXN</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4 font-semibold text-gray-900">Cuentas por cobrar abiertas</div>

            <div class="overflow-x-auto p-6">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Folio</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Vence</th>
                            <th class="px-4 py-3 text-right">Días vencido</th>
                            <th class="px-4 py-3">Rango</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Cobrado</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="border-b last:border-b-0 hover:bg-gray-50">
                                <td class="px-4 py-3">{{ $row->number }}</td>
                                <td class="px-4 py-3">{{ $row->customer_name ?: 'Sin cliente' }}</td>
                                <td class="px-4 py-3">{{ $row->due_date ?: '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->days_overdue }}</td>
                                <td class="px-4 py-3">{{ $row->bucket_label }}</td>
                                <td class="px-4 py-3 text-right">${{ number_format((float) $row->total, 2) }} {{ $row->currency }}</td>
                                <td class="px-4 py-3 text-right">${{ number_format((float) $row->collected_total, 2) }} {{ $row->currency }}</td>
                                <td class="px-4 py-3 text-right font-semibold">${{ number_format((float) $row->balance_total, 2) }} {{ $row->currency }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin cuentas por cobrar abiertas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
