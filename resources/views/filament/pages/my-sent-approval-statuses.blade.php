<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Documento</th>
                        <th class="px-4 py-3 text-left font-semibold">Folio</th>
                        <th class="px-4 py-3 text-left font-semibold">Estatus</th>
                        <th class="px-4 py-3 text-right font-semibold">Cantidad / Monto / Minutos</th>
                        <th class="px-4 py-3 text-left font-semibold">Enviado</th>
                        <th class="px-4 py-3 text-left font-semibold">Respuesta</th>
                        <th class="px-4 py-3 text-left font-semibold">Motivo / detalle</th>
                        <th class="px-4 py-3 text-right font-semibold">Acción</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ $row['document_label'] }}</td>

                            <td class="px-4 py-3 align-top font-medium">
                                {{ $row['document_number'] }}
                                @include('filament.partials.approval-priority-inline', ['row' => $row])
                            </td>

                            <td class="px-4 py-3 align-top">{{ $row['status_label'] }}</td>

                            <td class="px-4 py-3 align-top text-right">
                                {{ number_format((float) $row['amount_total'], 2) }}
                            </td>

                            <td class="px-4 py-3 align-top">
                                {{ $row['sent_at'] ? \Carbon\Carbon::parse($row['sent_at'])->format('d/m/Y H:i') : '—' }}
                            </td>

                            <td class="px-4 py-3 align-top">
                                {{ $row['completed_at'] ? \Carbon\Carbon::parse($row['completed_at'])->format('d/m/Y H:i') : '—' }}
                            </td>

                            <td class="px-4 py-3 align-top">
                                {{ $row['last_decision_reason'] ?: '—' }}
                            </td>

                            <td class="px-4 py-3 align-top text-right">
                                @if($row['url'] !== '#')
                                    <a href="{{ $row['url'] }}" class="font-semibold text-primary-600 hover:underline">Abrir</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                No tienes documentos enviados a aprobación.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
