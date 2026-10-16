<div class="space-y-4">
    <div class="text-sm font-semibold">
        {{ $product }}
    </div>

    <div class="text-sm text-gray-600 dark:text-gray-300">
        Almacén: {{ $warehouse }} | Ubicación: {{ $location }}
    </div>

    <div class="grid grid-cols-3 gap-3 text-sm">
        <div>
            <div class="text-gray-500">Física</div>
            <strong>{{ number_format($physical, 2) }}</strong>
        </div>
        <div>
            <div class="text-gray-500">Reservada</div>
            <strong>{{ number_format($reserved, 2) }}</strong>
        </div>
        <div>
            <div class="text-gray-500">Disponible</div>
            <strong>{{ number_format($available, 2) }}</strong>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="p-2">Documento</th>
                    <th class="p-2">Origen</th>
                    <th class="p-2 text-right">Cantidad</th>
                    <th class="p-2">Reservado el</th>
                    <th class="p-2">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reservations as $reservation)
                    <tr class="border-b">
                        <td class="p-2 font-medium">
                            {{ $reservation['folio'] }}
                        </td>
                        <td class="p-2">
                            {{ $reservation['source'] === 'pos_order' ? 'Ticket PDV' : $reservation['source'] }}
                        </td>
                        <td class="p-2 text-right">
                            {{ number_format($reservation['quantity'], 2) }}
                        </td>
                        <td class="p-2">
                            {{ $reservation['reserved_at'] ?: '—' }}
                        </td>
                        <td class="p-2">Activa</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-3 text-gray-500">
                            No se encontraron documentos de reserva activos
                            para esta combinación de producto y ubicación.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500">
        Consulta informativa. No modifica reservas ni existencias.
    </p>
</div>
