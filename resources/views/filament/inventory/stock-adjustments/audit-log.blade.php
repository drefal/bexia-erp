@php
    $summary = $audit['summary'] ?? [];
    $logs = $audit['logs'] ?? [];
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Auditoría del ajuste
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Folio: {{ $record->reference ?: ('Ajuste #' . $record->id) }}
                </p>
            </div>

            <span class="rounded-full px-3 py-1 text-xs font-medium
                @if($record->status === 'done') bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300
                @elseif($record->status === 'cancelled') bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300
                @else bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300
                @endif">
                {{ $record->status === 'done' ? 'Hecho' : ($record->status === 'cancelled' ? 'Cancelado' : 'Borrador') }}
            </span>
        </div>

        <dl class="grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach($summary as $item)
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $item['label'] ?? '' }}
                    </dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-gray-950 dark:text-white">
                        {{ $item['value'] ?? '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold text-gray-950 dark:text-white">
            Historial de acciones
        </h3>

        @if(empty($logs))
            <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                Este ajuste todavía no tiene registros en el historial de auditoría.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Acción</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Usuario</th>
                            <th class="px-3 py-2">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($logs as $log)
                            <tr class="text-gray-700 dark:text-gray-200">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $log['created_at'] ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $log['action_label'] ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $log['status_label'] ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $log['user_label'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $log['reason'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
