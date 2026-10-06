<x-filament-panels::page>
    <div class="space-y-6">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section>
                <div class="text-sm text-gray-500">
                    Cajas chicas
                </div>

                <div class="mt-1 text-2xl font-semibold">
                    {{ number_format($summary['funds'] ?? 0) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">
                    Monto autorizado
                </div>

                <div class="mt-1 text-2xl font-semibold">
                    ${{ number_format($summary['authorized'] ?? 0, 2) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">
                    Saldo operativo
                </div>

                <div class="mt-1 text-2xl font-semibold">
                    ${{ number_format($summary['operational'] ?? 0, 2) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500">
                    Diferencias
                </div>

                <div class="mt-1 text-2xl font-semibold">
                    {{ number_format($summary['differences'] ?? 0) }}
                </div>
            </x-filament::section>
        </div>

        @if (($summary['differences'] ?? 0) > 0)
            <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
                Hay cajas chicas cuyo saldo operativo no coincide con Tesorería.
                Revisa las filas marcadas como
                <strong>REVISAR</strong>.
            </div>
        @endif

        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left">
                            <th class="p-3">Folio</th>
                            <th class="p-3">Caja chica</th>
                            <th class="p-3">Responsable</th>
                            <th class="p-3 text-right">Autorizado</th>
                            <th class="p-3 text-right">Saldo operativo</th>
                            <th class="p-3 text-right">Saldo Tesorería</th>
                            <th class="p-3 text-right">Diferencia</th>
                            <th class="p-3 text-right">Fondeado</th>
                            <th class="p-3 text-right">Repuesto</th>
                            <th class="p-3 text-right">Gastado</th>
                            <th class="p-3 text-right">Devuelto</th>
                            <th class="p-3">Último movimiento</th>
                            <th class="p-3">Estado</th>
                            <th class="p-3">Integridad</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-b border-gray-100">
                                <td class="p-3 font-medium">
                                    <a
                                        href="{{ \App\Filament\Resources\PettyCashFundResource::getUrl('view', ['record' => $row->id]) }}"
                                        class="text-primary-600 hover:underline"
                                    >
                                        {{ $row->number ?: '—' }}
                                    </a>
                                </td>

                                <td class="p-3">
                                    {{ $row->name ?: '—' }}
                                </td>

                                <td class="p-3">
                                    {{ $row->employee_name ?: '—' }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->authorized_amount, 2) }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->operational_balance, 2) }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) ($row->treasury_balance ?? 0), 2) }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->difference, 2) }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->initial_funding_total, 2) }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->replenishment_total, 2) }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->expense_total, 2) }}
                                </td>

                                <td class="p-3 text-right">
                                    ${{ number_format((float) $row->return_total, 2) }}
                                </td>

                                <td class="p-3">
                                    {{ $row->last_movement_date ?: '—' }}
                                </td>

                                <td class="p-3">
                                    {{ match ($row->status) {
                                        'active' => 'Activa',
                                        'suspended' => 'Suspendida',
                                        'closed' => 'Cerrada',
                                        default => $row->status ?: '—',
                                    } }}
                                </td>

                                <td class="p-3">
                                    @if ($row->has_difference)
                                        <span class="font-semibold text-danger-600">
                                            REVISAR
                                        </span>
                                    @else
                                        <span class="font-semibold text-success-600">
                                            OK
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="p-8 text-center text-gray-500">
                                    No hay cajas chicas para esta empresa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
