@php
    $line->loadMissing(['concepts', 'employee']);
    $perceptions = $line->concepts->where('type', 'perception')->sum(fn ($concept) => (float) $concept->amount);
    $deductions = $line->concepts->where('type', 'deduction')->sum(fn ($concept) => (float) $concept->amount);
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="text-sm text-gray-500">Empleado</div>
        <div class="text-lg font-semibold">{{ $line->employee?->name ?? '-' }}</div>
        <div class="mt-2 grid gap-3 text-sm md:grid-cols-3">
            <div>
                <div class="text-gray-500">Percepciones</div>
                <div class="font-semibold">${{ number_format($perceptions, 2) }}</div>
            </div>
            <div>
                <div class="text-gray-500">Deducciones</div>
                <div class="font-semibold">${{ number_format($deductions, 2) }}</div>
            </div>
            <div>
                <div class="text-gray-500">Neto línea</div>
                <div class="font-semibold">${{ number_format((float) $line->net_amount, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left">Código</th>
                    <th class="px-3 py-2 text-left">Concepto</th>
                    <th class="px-3 py-2 text-left">Tipo</th>
                    <th class="px-3 py-2 text-left">Origen</th>
                    <th class="px-3 py-2 text-right">Cantidad</th>
                    <th class="px-3 py-2 text-right">Tarifa</th>
                    <th class="px-3 py-2 text-right">Importe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($line->concepts as $concept)
                    <tr>
                        <td class="px-3 py-2 font-mono text-xs">{{ $concept->code }}</td>
                        <td class="px-3 py-2">{{ $concept->name }}</td>
                        <td class="px-3 py-2">{{ $concept->type }}</td>
                        <td class="px-3 py-2">{{ $concept->source }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $concept->quantity, 4) }} {{ $concept->unit }}</td>
                        <td class="px-3 py-2 text-right">${{ number_format((float) $concept->rate, 4) }}</td>
                        <td class="px-3 py-2 text-right font-semibold">${{ number_format((float) $concept->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-gray-500">
                            Esta línea todavía no tiene conceptos generados. Recalcula la pre-nómina.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
