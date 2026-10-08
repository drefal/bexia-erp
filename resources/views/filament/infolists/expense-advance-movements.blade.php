@php
    $rows = $getState();

    if (! is_array($rows)) {
        $rows = [];
    }
@endphp

<div class="w-full">
    @if(count($rows) === 0)
        <div
            class="rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
        >
            No hay movimientos financieros registrados.
        </div>
    @else
        <div
            class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700"
        >
            <table class="w-full text-sm">
                <thead
                    class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
                >
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">
                            Movimiento
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            Fecha
                        </th>

                        <th class="px-4 py-3 text-right font-semibold">
                            Importe
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            Tipo
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            Movimiento Tesorería
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            Estado
                        </th>
                    </tr>
                </thead>

                <tbody
                    class="divide-y divide-gray-200 dark:divide-gray-700"
                >
                    @foreach($rows as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold"
                                >
                                    {{ $row['concept'] ?? '—' }}
                                </span>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $row['date'] ?? '—' }}
                            </td>

                            <td
                                class="px-4 py-3 text-right font-semibold whitespace-nowrap"
                            >
                                {{ $row['amount'] ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $row['direction'] ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $row['movement'] ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold"
                                >
                                    {{ $row['status'] ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
