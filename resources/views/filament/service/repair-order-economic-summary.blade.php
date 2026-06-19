@php
    use Illuminate\Support\Facades\DB;

    $recordId = (int) ($record?->getKey() ?? 0);

    $repair = $recordId > 0
        ? DB::table('repair_orders')->where('id', $recordId)->first()
        : null;

    $parts = $recordId > 0
        ? DB::table('repair_order_parts')->where('repair_order_id', $recordId)->orderBy('id')->get()
        : collect();

    $receivable = ($repair && ! empty($repair->account_receivable_id))
        ? DB::table('account_receivables')->where('id', (int) $repair->account_receivable_id)->first()
        : null;

    $money = fn ($value) => '$' . number_format((float) ($value ?? 0), 2);
    $percent = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2) . '%';

    $statusLabel = match ((string) ($repair->economic_status ?? '')) {
        'ready_to_charge' => 'Listo para cobrar',
        'needs_approval' => 'Requiere aprobación',
        'receivable_created' => 'Cuenta por cobrar creada',
        'partially_charged' => 'Cobro parcial',
        'charged' => 'Cobrada',
        default => 'Sin cierre económico',
    };

    $statusColor = match ((string) ($repair->economic_status ?? '')) {
        'ready_to_charge' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'needs_approval' => 'bg-amber-50 text-amber-800 border-amber-200',
        'receivable_created' => 'bg-blue-50 text-blue-800 border-blue-200',
        'partially_charged' => 'bg-amber-50 text-amber-800 border-amber-200',
        'charged' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        default => 'bg-gray-50 text-gray-700 border-gray-200',
    };
@endphp

<div class="space-y-5">
    @if (! $repair)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
            No se encontró la reparación.
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Cierre económico
                </div>

                <div class="mt-1 text-lg font-extrabold text-gray-950">
                    {{ $repair->folio ?? ('Reparación #' . $repair->id) }}
                </div>
            </div>

            <div class="rounded-full border px-3 py-1 text-sm font-bold {{ $statusColor }}">
                {{ $statusLabel }}
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Refacciones venta</div>
                <div class="mt-2 text-2xl font-extrabold text-gray-950">{{ $money($repair->parts_sale_total ?? 0) }}</div>
                <div class="mt-1 text-xs text-gray-500">Costo: {{ $money($repair->parts_cost_total ?? 0) }}</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ganancia refacciones</div>
                <div class="mt-2 text-2xl font-extrabold text-emerald-700">{{ $money($repair->parts_profit_amount ?? 0) }}</div>
                <div class="mt-1 text-xs text-gray-500">Margen: {{ $percent($repair->parts_profit_percent ?? null) }}</div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Mano de obra</div>
                <div class="mt-2 text-2xl font-extrabold text-gray-950">{{ $money($repair->labor_sale_total ?? 0) }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ number_format((float) ($repair->actual_labor_hours ?? 0), 2) }} h x {{ $money($repair->labor_hour_rate ?? 0) }}
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ganancia mano de obra</div>
                <div class="mt-2 text-2xl font-extrabold text-emerald-700">{{ $money($repair->labor_profit_amount ?? 0) }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    Costo interno: {{ $money($repair->labor_cost_total ?? 0) }}
                    @if (($repair->labor_profit_percent ?? null) !== null)
                        · Margen: {{ $percent($repair->labor_profit_percent ?? null) }}
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total final</div>
                <div class="mt-2 text-2xl font-extrabold text-primary-700">{{ $money($repair->economic_total ?? $repair->total_amount ?? 0) }}</div>
                <div class="mt-1 text-xs text-gray-500">IVA: {{ $money($repair->economic_tax ?? 0) }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-3 text-sm font-bold text-gray-950">
                    Resumen del cierre
                </div>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Subtotal económico</dt>
                        <dd class="font-semibold text-gray-950">{{ $money($repair->economic_subtotal ?? 0) }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">IVA {{ number_format((float) ($repair->economic_tax_rate ?? 16), 2) }}%</dt>
                        <dd class="font-semibold text-gray-950">{{ $money($repair->economic_tax ?? 0) }}</dd>
                    </div>

                    <div class="flex justify-between gap-4 border-t border-gray-100 pt-2">
                        <dt class="text-gray-950 font-bold">Total a cobrar</dt>
                        <dd class="font-extrabold text-primary-700">{{ $money($repair->economic_total ?? $repair->total_amount ?? 0) }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Ganancia total</dt>
                        <dd class="font-semibold text-emerald-700">
                            {{ $money($repair->total_profit_amount ?? 0) }}
                            <span class="text-xs text-gray-500">({{ $percent($repair->total_profit_percent ?? null) }})</span>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Requiere aprobación</dt>
                        <dd class="font-semibold {{ (bool) ($repair->economic_requires_approval ?? false) ? 'text-amber-700' : 'text-emerald-700' }}">
                            {{ (bool) ($repair->economic_requires_approval ?? false) ? 'Sí' : 'No' }}
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Diferencia vs aprobado</dt>
                        <dd class="font-semibold text-gray-950">
                            {{ $money($repair->economic_difference_amount ?? 0) }}
                            <span class="text-xs text-gray-500">({{ $percent($repair->economic_difference_percent ?? 0) }})</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-3 text-sm font-bold text-gray-950">
                    Estado de cobro
                </div>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Estado económico</dt>
                        <dd class="font-semibold text-gray-950">{{ $statusLabel }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Fecha cierre económico</dt>
                        <dd class="font-semibold text-gray-950">{{ $repair->economic_closed_at ?? 'Pendiente' }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Lista para cobrar</dt>
                        <dd class="font-semibold text-gray-950">{{ $repair->ready_to_charge_at ?? 'No' }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Total guardado</dt>
                        <dd class="font-semibold text-gray-950">{{ $money($repair->total_amount ?? 0) }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Cuenta por cobrar</dt>
                        <dd class="font-semibold text-gray-950">
                            @if ($receivable)
                                {{ $receivable->number ?? ('CxC #' . $receivable->id) }}
                            @else
                                No generada
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! empty($repair->economic_difference_reason))
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <strong>Motivo de diferencia:</strong>
                        {{ $repair->economic_difference_reason }}
                    </div>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-3 text-sm font-bold text-gray-950">
                Refacciones y ganancia
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Refacción</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Costo unit.</th>
                            <th class="px-4 py-3 text-right">Precio unit.</th>
                            <th class="px-4 py-3 text-right">Costo total</th>
                            <th class="px-4 py-3 text-right">Venta total</th>
                            <th class="px-4 py-3 text-right">Ganancia</th>
                            <th class="px-4 py-3 text-right">Margen</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @forelse ($parts as $part)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-950">
                                    {{ $part->description ?? ('Refacción #' . $part->id) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ number_format((float) ($part->economic_quantity ?? $part->quantity ?? 0), 4) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ $money($part->unit_cost ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ $money($part->unit_price ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ $money($part->line_cost_total ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    {{ $money($part->line_sale_total ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-700">
                                    {{ $money($part->line_profit_amount ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-950">
                                    {{ $percent($part->line_profit_percent ?? null) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                    No hay refacciones registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
