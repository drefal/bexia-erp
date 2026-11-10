{{-- BEXIA_POS_ACCESS9_FIX4A_ADMIN --}}
@if($canViewAccess9Shifts && $access9ShiftRows->isNotEmpty())
<section id="bexia-access9-shifts"
    class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">

    <div class="mb-4">
        <h2 class="text-lg font-bold">
            Cortes individuales de cajeros
        </h2>
        <p class="text-sm text-gray-500">
            Turnos registrados en esta sesión física.
            Reportes de cobros; no representan un arqueo del
            efectivo compartido.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="p-2">Turno</th>
                    <th class="p-2">Cajero</th>
                    <th class="p-2">Inicio</th>
                    <th class="p-2">Fin</th>
                    <th class="p-2">Estado</th>
                    <th class="p-2">Tickets</th>
                    <th class="p-2">Pagos</th>
                    <th class="p-2 text-right">Cobrado</th>
                    <th class="p-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($access9ShiftRows as $shift)
                    <tr class="border-b dark:border-gray-700">
                        <td class="p-2">#{{ $shift['id'] }}</td>
                        <td class="p-2">{{ $shift['employee'] }}</td>
                        <td class="p-2">{{ $shift['opened_at'] }}</td>
                        <td class="p-2">
                            {{ $shift['closed_at'] ?? 'En curso' }}
                        </td>
                        <td class="p-2">
                            {{ $shift['status'] === 'closed' ? 'Cerrado' : 'Abierto' }}
                        </td>
                        <td class="p-2">{{ $shift['ticket_count'] }}</td>
                        <td class="p-2">{{ $shift['payment_count'] }}</td>
                        <td class="p-2 text-right font-semibold">
                            ${{ number_format($shift['total'], 2) }}
                        </td>
                        <td class="p-2">
                            <div class="flex flex-wrap gap-2">
                                <a class="font-semibold text-primary-600 underline"
                                   target="_blank" rel="noopener"
                                   href="{{ route('pos.cashier-shifts.report', ['shift' => $shift['id'], 'format' => '80mm']) }}">
                                   Ver / 80 mm
                                </a>
                                <a class="font-semibold text-primary-600 underline"
                                   target="_blank" rel="noopener"
                                   href="{{ route('pos.cashier-shifts.report', ['shift' => $shift['id'], 'format' => 'carta']) }}">
                                   Carta / PDF
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-amber-700">
        Reporte preliminar: devoluciones y retiros
        pendientes de conciliación individual.
    </p>
</section>
@endif
