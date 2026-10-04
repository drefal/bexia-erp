<x-filament-panels::page>

    {{-- CIBER3G2_PAYMENT_SYNC_UI --}}
    <div
        wire:poll.10s="syncPosStatuses"
        style="display:none"
        aria-hidden="true"
    ></div>

    <div
        wire:poll.10s
        class="space-y-6"
    >

        {{-- CIBER3I2_UI_PERMISSIONS --}}
        @php
            $canOperateComputerRental =
                $this->canOperateComputerRental();

            $canManageComputerRental =
                $this->canManageComputerRental();
        @endphp

        <div class="grid gap-4 md:grid-cols-3">

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500">
                    Estaciones
                </div>

                <div class="mt-2 text-3xl font-bold">
                    {{ $this->stations->count() }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500">
                    En uso
                </div>

                <div class="mt-2 text-3xl font-bold">
                    {{ $this->stations->where('status', 'in_use')->count() }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-sm font-medium text-gray-500">
                    Pendientes de PDV
                </div>

                <div class="mt-2 text-3xl font-bold">
                    {{ $this->pendingPosRentalsCount }}
                </div>
            </div>

        </div>


        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            <div class="mb-4 text-lg font-bold">
                Equipos
            </div>

            @if($this->stations->isEmpty())

                <div class="rounded-lg bg-gray-50 p-6 text-center text-gray-500 dark:bg-gray-800">
                    Todavía no hay estaciones configuradas.
                </div>

            @else

                <div class="grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                    {{-- CIBER3D2_STATION_PASTELS --}}
                    @foreach($this->stations as $station)
                        {{-- CIBER3D2_PASTEL_PALETTE --}}
                        @php
                            $stationPastels = [
                                [
                                    'light' => 'background:#eff6ff;border-color:#bfdbfe;',
                                    'dark'  => 'dark:bg-blue-950/30 dark:ring-blue-800/50',
                                ],
                                [
                                    'light' => 'background:#f0fdf4;border-color:#bbf7d0;',
                                    'dark'  => 'dark:bg-green-950/30 dark:ring-green-800/50',
                                ],
                                [
                                    'light' => 'background:#fefce8;border-color:#fde68a;',
                                    'dark'  => 'dark:bg-yellow-950/30 dark:ring-yellow-800/50',
                                ],
                                [
                                    'light' => 'background:#fdf2f8;border-color:#fbcfe8;',
                                    'dark'  => 'dark:bg-pink-950/30 dark:ring-pink-800/50',
                                ],
                                [
                                    'light' => 'background:#faf5ff;border-color:#e9d5ff;',
                                    'dark'  => 'dark:bg-purple-950/30 dark:ring-purple-800/50',
                                ],
                                [
                                    'light' => 'background:#ecfeff;border-color:#a5f3fc;',
                                    'dark'  => 'dark:bg-cyan-950/30 dark:ring-cyan-800/50',
                                ],
                            ];

                            $stationPastel =
                                $stationPastels[$loop->index % count($stationPastels)];
                        @endphp

                        @php
                            $active = $this->activeSessionForStation($station->id);
                            $estimate = $active
                                ? $this->liveEstimate($active)
                                : null;
                        @endphp

                        <div class="rounded-xl border p-5 dark:border-gray-700 border transition-shadow hover:shadow-md {{ $stationPastel["dark"] }}" style="{{ $stationPastel['light'] }}">

                            <div class="flex items-start justify-between gap-3">

                                <div>
                                    <div class="text-xl font-bold">
                                        {{ $station->code }}
                                    </div>

                                    <div class="text-sm text-gray-500">
                                        {{ $station->name }}
                                    </div>

                                    {{-- CIBER3E_EDIT_STATION_UI --}}
                                    @if($canManageComputerRental)

                                        <button
                                            type="button"
                                            wire:click="beginEditStation({{ $station->id }})"
                                            class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline"
                                        >
                                            Editar PC
                                        </button>

                                    @endif
                                </div>

                                @if($station->status === 'available')
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">
                                        Disponible
                                    </span>
                                @elseif($station->status === 'in_use')
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                        En uso
                                    </span>
                                @elseif($station->status === 'maintenance')
                                    <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700">
                                        Mantenimiento
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-700">
                                        {{ $station->status }}
                                    </span>
                                @endif

                            </div>


                            @if($active)

                                <div class="mt-5 space-y-2 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">

                                    <div>
                                        <span class="text-sm text-gray-500">Inicio:</span>
                                        <strong>{{ $active->started_at?->format('H:i:s') }}</strong>
                                    </div>

                                    <div>
                                        <span class="text-sm text-gray-500">Tiempo real:</span>

                                        <strong>
                                            {{ gmdate(
                                                'H:i:s',
                                                (int) ($estimate['duration_seconds'] ?? 0)
                                            ) }}
                                        </strong>
                                    </div>

                                    <div>
                                        <span class="text-sm text-gray-500">Minutos facturables:</span>
                                        <strong>{{ $estimate['billable_minutes'] ?? 0 }}</strong>
                                    </div>

                                    <div>
                                        <span class="text-sm text-gray-500">Importe actual:</span>

                                        <strong class="text-lg">
                                            ${{ number_format(
                                                (float) ($estimate['amount'] ?? 0),
                                                2
                                            ) }}
                                        </strong>
                                    </div>

                                </div>

                                {{-- CIBER3D_OPEN_ACCOUNT_UI --}}
                                @php
                                    $consumptionLines = $this->consumptionLinesForSession($active->id);
                                    $consumptionTotal = $this->consumptionTotalForSession($active->id);
                                @endphp

                                <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">

                                    <div class="flex items-center justify-between gap-3">
                                        <div class="font-semibold">
                                            Consumos de la cuenta
                                        </div>

                                        <div class="text-sm font-bold">
                                            ${{ number_format($consumptionTotal, 2) }}
                                        </div>
                                    </div>

                                    @if($consumptionLines->isNotEmpty())

                                        <div class="mt-3 space-y-2">

                                            @foreach($consumptionLines as $line)

                                                <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">

                                                    <div class="min-w-0">
                                                        <div class="font-medium">
                                                            {{ $line->description }}
                                                        </div>

                                                        <div class="text-xs text-gray-500">
                                                            {{ number_format((float) $line->quantity, 2) }}
                                                            ×
                                                            ${{ number_format((float) $line->unit_price, 2) }}
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-3">

                                                        <div class="font-semibold">
                                                            ${{ number_format((float) $line->total, 2) }}
                                                        </div>

                                                        <x-filament::button
                                                            type="button"
                                                            size="xs"
                                                            color="danger"
                                                            outlined
                                                            wire:click="beginRemoveConsumption({{ $line->id }})"
                                                        >
                                                            Quitar
                                                        </x-filament::button>

                                                    </div>

                                                </div>

                                            @endforeach

                                        </div>

                                    @else

                                        <div class="mt-2 text-sm text-gray-500">
                                            Todavía no hay consumos adicionales.
                                        </div>

                                    @endif


                                    {{-- CIBER3I2C_CONSUMPTION_PERMISSION --}}
                                    @if($canOperateComputerRental)
                                    <div class="mt-4 grid gap-2 md:grid-cols-[1fr_110px_auto]">

                                        <select
                                            wire:model="consumptionProductIds.{{ $active->id }}"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                        >
                                            <option value="">Agregar servicio...</option>

                                            @foreach($this->consumptionServiceProducts as $product)
                                                <option value="{{ $product->id }}">
                                                    {{ $product->name }}
                                                    ·
                                                    {{-- CIBER3D1_GROSS_PRICE_UI --}}
                                                    ${{ number_format($this->consumptionGrossPrice($product), 2) }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            wire:model="consumptionQuantities.{{ $active->id }}"
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                            placeholder="Cant."
                                        >

                                        <x-filament::button
                                            type="button"
                                            wire:click="addConsumption({{ $active->id }})"
                                        >
                                            Agregar
                                        </x-filament::button>

                                    </div>

                                    @endif


                                    <div class="mt-4 border-t pt-3 dark:border-gray-700">

                                        <div class="flex items-center justify-between text-sm">
                                            <span>Renta actual</span>
                                            <strong>
                                                ${{ number_format((float) ($estimate['amount'] ?? 0), 2) }}
                                            </strong>
                                        </div>

                                        <div class="mt-1 flex items-center justify-between text-sm">
                                            <span>Consumos</span>
                                            <strong>
                                                ${{ number_format($consumptionTotal, 2) }}
                                            </strong>
                                        </div>

                                        <div class="mt-2 flex items-center justify-between text-lg">
                                            <strong>Total actual</strong>

                                            <strong>
                                                ${{
                                                    number_format(
                                                        (float) ($estimate['amount'] ?? 0)
                                                        + $consumptionTotal,
                                                        2
                                                    )
                                                }}
                                            </strong>
                                        </div>

                                    </div>

                                </div>


                                <div class="mt-4 flex gap-2">

                                    @if($canOperateComputerRental)

                                        <x-filament::button
                                            type="button"
                                            wire:click="beginFinishRental({{ $active->id }})"
                                        >
                                            Finalizar renta
                                        </x-filament::button>

                                    @endif

                                    @php
                                        $graceInfo =
                                            $this->cancellationGraceInfo(
                                                $active
                                            );
                                    @endphp

                                    @if(
                                        $canOperateComputerRental
                                        && $graceInfo['allowed']
                                    )

                                        <x-filament::button
                                            type="button"
                                            color="gray"
                                            wire:click="openCancel({{ $active->id }})"
                                        >
                                            Cancelar sin cobro
                                        </x-filament::button>

                                    @elseif($graceInfo['has_consumptions'])

                                        <span class="text-xs text-gray-500">
                                            Cancelación gratis no disponible:
                                            hay consumos
                                        </span>

                                    @elseif($graceInfo['grace_minutes'] > 0)

                                        <span class="text-xs text-gray-500">
                                            Cortesía de
                                            {{ $graceInfo['grace_minutes'] }}
                                            min vencida
                                        </span>

                                    @endif

                                </div>

                            @elseif($station->status === 'available')

                                <div class="mt-5">

                                    <label class="mb-1 block text-sm font-medium">
                                        Tarifa
                                    </label>

                                    <select
                                        wire:model="selectedRates.{{ $station->id }}"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                    >
                                        <option value="">Seleccionar</option>

                                        @foreach($this->rates as $rate)
                                            <option value="{{ $rate->id }}">
                                                {{ $rate->name }}
                                                @if($rate->billing_mode === 'open')
                                                    · ${{ number_format((float) $rate->hourly_rate, 2) }}/h
                                                @else
                                                    · {{ $rate->prepaid_minutes }} min
                                                    · ${{ number_format((float) $rate->prepaid_price, 2) }}
                                                @endif
                                            </option>
                                        @endforeach

                                    </select>

                                    @if($canOperateComputerRental)

                                        <div class="mt-3">
                                            <x-filament::button
                                                type="button"
                                                wire:click="startRental({{ $station->id }})"
                                            >
                                                Iniciar renta
                                            </x-filament::button>
                                        </div>

                                    @endif

                                </div>

                            @endif

                        </div>

                    @endforeach

                </div>

            @endif

        </div>


        {{-- CIBER3G2_RENTAL_POS_STATUS --}}
        <div
            class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">

                <div>
                    <div class="text-lg font-bold">
                        Rentas recientes y cobro PDV
                    </div>

                    <div class="text-sm text-gray-500">
                        Seguimiento del ticket, pagos y saldo.
                    </div>
                </div>

                <x-filament::button
                    type="button"
                    size="sm"
                    color="gray"
                    wire:click="syncPosStatuses"
                >
                    Actualizar estados
                </x-filament::button>

            </div>


            @php
                $posRentalSessions =
                    $this->recentSessions
                        ->filter(
                            fn ($session) =>
                                $session->pos_order_id
                                || in_array(
                                    $session->status,
                                    [
                                        'pending_pos',
                                        'sent_to_pos',
                                        'paid',
                                    ],
                                    true
                                )
                        );
            @endphp


            @if($posRentalSessions->isEmpty())

                <div
                    class="rounded-lg bg-gray-50 p-5 text-center text-sm text-gray-500 dark:bg-gray-800"
                >
                    Todavía no hay rentas enviadas al Punto de Venta.
                </div>

            @else

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">

                    @foreach($posRentalSessions as $rentalSession)

                        @php
                            $posSummary =
                                $this->rentalPosSummary(
                                    $rentalSession
                                );

                            $rentalConsumptionTotal =
                                $this->consumptionTotalForSession(
                                    $rentalSession->id
                                );

                            $rentalGrandTotal =
                                (float) $rentalSession->amount
                                + $rentalConsumptionTotal;

                            $statusIsPaid =
                                $posSummary['status']
                                === 'paid';

                            $statusIsPartial =
                                $posSummary['status']
                                    === 'pending_payment'
                                && $posSummary['paid'] > 0;

                            $statusIsPending =
                                $posSummary['status']
                                    === 'pending_payment'
                                && $posSummary['paid'] <= 0;

                            /** CIBER3H1_CANCELLED_UI */
                            $statusIsCancelled =
                                in_array(
                                    $posSummary['status'],
                                    ['cancelled', 'canceled'],
                                    true
                                );
                        @endphp

                        <div
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/60"
                        >

                            <div class="flex items-start justify-between gap-3">

                                <div>
                                    <div class="font-bold">
                                        {{ $rentalSession->station?->code ?? 'PC' }}
                                    </div>

                                    <div class="text-xs text-gray-500">
                                        {{ $rentalSession->billable_minutes }} min facturables
                                    </div>
                                </div>


                                @if($statusIsPaid)

                                    <span
                                        class="rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-700"
                                    >
                                        Pagado
                                    </span>

                                @elseif($statusIsPartial)

                                    <span
                                        class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-700"
                                    >
                                        Pago parcial
                                    </span>

                                @elseif($statusIsPending)

                                    <span
                                        class="rounded-full bg-blue-100 px-2 py-1 text-xs font-bold text-blue-700"
                                    >
                                        Pendiente de cobro
                                    </span>

                                @elseif($statusIsCancelled)

                                    <span
                                        class="rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-700"
                                    >
                                        Ticket cancelado
                                    </span>

                                @else

                                    <span
                                        class="rounded-full bg-gray-200 px-2 py-1 text-xs font-bold text-gray-700"
                                    >
                                        {{ $posSummary['status_label'] }}
                                    </span>

                                @endif

                            </div>


                            <div class="mt-4 space-y-1 text-sm">

                                <div class="flex justify-between gap-3">
                                    <span>Renta</span>

                                    <strong>
                                        ${{ number_format((float) $rentalSession->amount, 2) }}
                                    </strong>
                                </div>

                                <div class="flex justify-between gap-3">
                                    <span>Consumos</span>

                                    <strong>
                                        ${{ number_format($rentalConsumptionTotal, 2) }}
                                    </strong>
                                </div>

                                <div
                                    class="flex justify-between gap-3 border-t pt-2 font-bold dark:border-gray-700"
                                >
                                    <span>Total</span>

                                    <span>
                                        ${{ number_format($rentalGrandTotal, 2) }}
                                    </span>
                                </div>

                            </div>


                            @if($posSummary['exists'])

                                <div
                                    class="mt-4 rounded-lg bg-white p-3 text-sm dark:bg-gray-900"
                                >

                                    <div class="font-semibold">
                                        Ticket
                                        {{ $posSummary['number'] ?: ('#' . $posSummary['id']) }}
                                    </div>

                                    <div class="mt-2 flex justify-between text-xs">
                                        <span>Pagado</span>
                                        <strong>
                                            ${{ number_format($posSummary['paid'], 2) }}
                                        </strong>
                                    </div>

                                    <div class="mt-1 flex justify-between text-xs">
                                        <span>Saldo</span>
                                        <strong>
                                            ${{ number_format($posSummary['balance'], 2) }}
                                        </strong>
                                    </div>

                                    @if($posSummary['paid_at'])

                                        <div class="mt-2 text-xs text-gray-500">
                                            Cobrado:
                                            {{ \Illuminate\Support\Carbon::parse($posSummary['paid_at'])->format('d/m/Y H:i') }}
                                        </div>

                                    @endif


                                    @if($statusIsCancelled)

                                        <div
                                            class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/30 dark:text-red-200"
                                        >

                                            <div class="font-bold">
                                                Este ticket fue cancelado en PDV.
                                            </div>

                                            @if($posSummary['cancelled_at'])

                                                <div class="mt-1">
                                                    Cancelado:
                                                    {{ \Illuminate\Support\Carbon::parse($posSummary['cancelled_at'])->format('d/m/Y H:i') }}
                                                </div>

                                            @endif

                                            @if($posSummary['cancel_reason'])

                                                <div class="mt-1">
                                                    Motivo:
                                                    {{ $posSummary['cancel_reason'] }}
                                                </div>

                                            @endif

                                            <div class="mt-2">
                                                La renta permanece finalizada.
                                                La PC no se vuelve a abrir automáticamente.
                                            </div>

                                            @if($canOperateComputerRental)

                                                <div class="mt-3">

                                                    <x-filament::button
                                                        type="button"
                                                        size="sm"
                                                        color="danger"
                                                        wire:click="beginResendCancelledTicket({{ $rentalSession->id }})"
                                                    >
                                                        Generar nuevo ticket
                                                    </x-filament::button>

                                                </div>

                                            @endif

                                        </div>

                                    @endif

                                </div>

                            @endif

                        </div>

                    @endforeach

                </div>

            @endif

        </div>


        {{-- CIBER3F_RATE_MANAGER_UI --}}
        <div
            class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">

                <div>
                    <div class="text-lg font-bold">
                        Tarifas configuradas
                    </div>

                    <div class="text-sm text-gray-500">
                        Los cambios aplican a nuevas rentas.
                        Las rentas ya iniciadas conservan sus condiciones originales.
                    </div>
                </div>

                <div
                    class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold dark:bg-gray-800"
                >
                    {{ $this->allRates->count() }}
                </div>

            </div>


            @if($this->allRates->isEmpty())

                <div
                    class="rounded-lg bg-gray-50 p-5 text-center text-sm text-gray-500 dark:bg-gray-800"
                >
                    No hay tarifas configuradas.
                </div>

            @else

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">

                    @foreach($this->allRates as $rate)

                        @php
                            $assignedStations =
                                $this->rateStationCount($rate->id);
                        @endphp

                        <div
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/60"
                        >

                            <div class="flex items-start justify-between gap-3">

                                <div class="min-w-0">

                                    <div class="font-bold">
                                        {{ $rate->name }}
                                    </div>

                                    <div class="mt-1 text-xs text-gray-500">
                                        @if($rate->billing_mode === 'prepaid')

                                            Prepago ·
                                            {{ $rate->prepaid_minutes }} min ·
                                            ${{ number_format((float) $rate->prepaid_price, 2) }}

                                        @else

                                            Tiempo abierto ·
                                            ${{ number_format((float) $rate->hourly_rate, 2) }}/h

                                        @endif
                                    </div>

                                </div>


                                @if($rate->is_active)

                                    <span
                                        class="rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-700"
                                    >
                                        Activa
                                    </span>

                                @else

                                    <span
                                        class="rounded-full bg-gray-200 px-2 py-1 text-xs font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    >
                                        Inactiva
                                    </span>

                                @endif

                            </div>


                            @if($rate->billing_mode === 'open')

                                <div class="mt-3 text-sm">
                                    Mínimo:
                                    <strong>
                                        {{ $rate->minimum_minutes }} min
                                    </strong>

                                    · Fracción:
                                    <strong>
                                        {{ $rate->billing_increment_minutes }} min
                                    </strong>
                                </div>

                            @endif


                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">

                                Producto PDV:
                                <strong>
                                    {{ $rate->product?->name ?? 'Sin asignar' }}
                                </strong>

                                <div class="mt-1">
                                    Cancelación sin cobro:
                                    <strong>
                                        {{ (int) ($rate->cancellation_grace_minutes ?? 5) }} min
                                    </strong>
                                </div>

                            </div>


                            <div class="mt-1 text-xs text-gray-500">

                                @if($assignedStations === 1)

                                    Asignada a 1 PC activa

                                @elseif($assignedStations > 1)

                                    Asignada a {{ $assignedStations }} PCs activas

                                @else

                                    Sin PCs asignadas

                                @endif

                            </div>


                            @if($canManageComputerRental)

                                <div class="mt-4">

                                    <x-filament::button
                                        type="button"
                                        size="sm"
                                        color="gray"
                                        wire:click="beginEditRate({{ $rate->id }})"
                                    >
                                        Editar tarifa
                                    </x-filament::button>

                                </div>

                            @endif

                        </div>

                    @endforeach

                </div>

            @endif

        </div>


        @if($canManageComputerRental)

            <div class="grid gap-6 xl:grid-cols-2">

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                <div class="mb-4 text-lg font-bold">
                    Nueva tarifa
                </div>

                {{-- CIBER3B6A_RATE_ERRORS --}}
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-danger-300 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-950/30">
                        <div class="font-semibold text-danger-700 dark:text-danger-300">
                            No se pudo crear la tarifa:
                        </div>

                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-danger-700 dark:text-danger-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-4">

                    <div>
                        <label class="block text-sm font-medium">Nombre</label>
                        <input
                            type="text"
                            wire:model="rateName"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            placeholder="Internet estándar"
                        >
                        @error('rateName')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Tipo</label>

                        <select
                            wire:model.live="rateBillingMode"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                        >
                        {{-- CIBER3B6A_ERROR_rateBillingMode --}}
                        @error('rateBillingMode')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                            <option value="open">Tiempo abierto</option>
                            <option value="prepaid">Prepago / paquete</option>
                        </select>
                    </div>

                    @if($rateBillingMode === 'open')

                        <div>
                            <label class="block text-sm font-medium">
                                Precio por hora
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                wire:model="rateHourlyRate"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            >
                        {{-- CIBER3B6A_ERROR_rateHourlyRate --}}
                        @error('rateHourlyRate')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">

                            <div>
                                <label class="block text-sm font-medium">
                                    Mínimo minutos
                                </label>

                                <input
                                    type="number"
                                    min="1"
                                    wire:model="rateMinimumMinutes"
                                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                >
                        {{-- CIBER3B6A_ERROR_rateMinimumMinutes --}}
                        @error('rateMinimumMinutes')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium">
                                    Fracción minutos
                                </label>

                                <input
                                    type="number"
                                    min="1"
                                    wire:model="rateIncrementMinutes"
                                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                >
                        {{-- CIBER3B6A_ERROR_rateIncrementMinutes --}}
                        @error('rateIncrementMinutes')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                            </div>

                        </div>

                    @else

                        <div class="grid grid-cols-2 gap-3">

                            <div>
                                <label class="block text-sm font-medium">
                                    Minutos
                                </label>

                                <input
                                    type="number"
                                    min="1"
                                    wire:model="ratePrepaidMinutes"
                                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                >
                        {{-- CIBER3B6A_ERROR_ratePrepaidMinutes --}}
                        @error('ratePrepaidMinutes')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium">
                                    Precio
                                </label>

                                <input
                                    type="number"
                                    step="0.01"
                                    wire:model="ratePrepaidPrice"
                                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                >
                        {{-- CIBER3B6A_ERROR_ratePrepaidPrice --}}
                        @error('ratePrepaidPrice')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                            </div>

                        </div>

                    @endif

                    <div>

                        <label class="block text-sm font-medium">
                            Cancelación sin cobro
                        </label>

                        <div class="mt-1 flex items-center gap-2">

                            <input
                                type="number"
                                min="0"
                                max="60"
                                wire:model="rateCancellationGraceMinutes"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            >

                            <span class="whitespace-nowrap text-sm text-gray-500">
                                min
                            </span>

                        </div>

                        <div class="mt-1 text-xs text-gray-500">
                            0 desactiva la cancelación gratuita.
                        </div>

                        @error('rateCancellationGraceMinutes')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div>
                        <label class="block text-sm font-medium">
                            Producto servicio para PDV
                        </label>

                        <select
                            wire:model="rateProductId"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                        >
                        {{-- CIBER3B6A_ERROR_rateProductId --}}
                        @error('rateProductId')
                            <div class="mt-1 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                            <option value="">Sin asignar todavía</option>

                            @foreach($this->serviceProducts as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <x-filament::button
                        type="button"
                        wire:click="createRate"
                    >
                        Crear tarifa
                    </x-filament::button>

                </div>

            </div>


            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

                <div class="mb-4 text-lg font-bold">
                    Nueva estación
                </div>

                <div class="space-y-4">

                    <div>
                        <label class="block text-sm font-medium">Código</label>

                        <input
                            type="text"
                            wire:model="stationCode"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            placeholder="PC-01"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Nombre</label>

                        <input
                            type="text"
                            wire:model="stationName"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            placeholder="Computadora 01"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium">
                            Tarifa predeterminada
                        </label>

                        <select
                            wire:model="stationDefaultRateId"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <option value="">Sin tarifa</option>

                            @foreach($this->rates as $rate)
                                <option value="{{ $rate->id }}">
                                    {{ $rate->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">
                            Punto de venta
                        </label>

                        <select
                            wire:model="stationPosPointId"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <option value="">Sin asignar</option>

                            @foreach($this->posPoints as $pos)
                                <option value="{{ $pos->id }}">
                                    {{ $pos->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <x-filament::button
                        type="button"
                        wire:click="createStation"
                    >
                        Crear estación
                    </x-filament::button>

                </div>

            </div>

            </div>

        @endif


        {{-- CIBER3H2B_GRACE_VIEW --}}
        @if(
            $cancelSessionId
            && $canOperateComputerRental
        )

            @php
                $cancelSession =
                    $this->cancellingSession;

                $cancelGrace =
                    $cancelSession
                        ? $this->cancellationGraceInfo(
                            $cancelSession
                        )
                        : null;

                $remainingMinutes =
                    $cancelGrace
                        ? intdiv(
                            (int) $cancelGrace['remaining_seconds'],
                            60
                        )
                        : 0;

                $remainingSeconds =
                    $cancelGrace
                        ? (
                            (int) $cancelGrace['remaining_seconds']
                            % 60
                        )
                        : 0;
            @endphp

            <div
                role="dialog"
                aria-modal="true"
                style="
                    position: fixed;
                    inset: 0;
                    z-index: 100003;
                    overflow-y: auto;
                "
            >

                <button
                    type="button"
                    aria-label="Cerrar"
                    wire:click="$set('cancelSessionId', null)"
                    style="
                        position: fixed;
                        inset: 0;
                        width: 100vw;
                        height: 100vh;
                        border: 0;
                        background: rgba(15,23,42,.72);
                        backdrop-filter: blur(2px);
                        -webkit-backdrop-filter: blur(2px);
                        z-index: 1;
                    "
                ></button>

                <div
                    style="
                        position: relative;
                        z-index: 2;
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 24px;
                        pointer-events: none;
                    "
                >

                    <div
                        class="rounded-2xl bg-white p-6 dark:bg-gray-900"
                        style="
                            position: relative;
                            z-index: 3;
                            width: 100%;
                            max-width: 560px;
                            background: white;
                            border-radius: 18px;
                            box-shadow:
                                0 30px 80px rgba(0,0,0,.38),
                                0 8px 24px rgba(0,0,0,.22);
                            pointer-events: auto;
                        "
                    >

                        <div class="text-xl font-bold">
                            Cancelar renta sin cobro
                        </div>

                        @if($cancelSession)

                            <div class="mt-1 text-sm text-gray-500">
                                {{ $cancelSession->station?->code }}
                                ·
                                {{ $cancelSession->station?->name }}
                            </div>

                        @endif


                        @if($cancelGrace && $cancelGrace['allowed'])

                            <div
                                class="mt-4 rounded-xl bg-green-50 p-4 text-sm text-green-800"
                            >
                                Esta renta todavía está dentro de los
                                <strong>
                                    {{ $cancelGrace['grace_minutes'] }}
                                    minutos
                                </strong>
                                de cortesía.

                                <div class="mt-1 font-semibold">
                                    Tiempo restante:
                                    {{ sprintf(
                                        '%02d:%02d',
                                        $remainingMinutes,
                                        $remainingSeconds
                                    ) }}
                                </div>

                                No se generará ningún cobro.
                            </div>

                        @elseif($cancelGrace && $cancelGrace['has_consumptions'])

                            <div
                                class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800"
                            >
                                Esta cuenta ya tiene consumos adicionales.
                                No puede cancelarse gratuitamente.
                            </div>

                        @else

                            <div
                                class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800"
                            >
                                El periodo de cortesía ya terminó.
                                Finaliza la renta para cobrarla normalmente.
                            </div>

                        @endif


                        <div class="mt-5">

                            <label class="block text-sm font-medium">
                                Motivo
                            </label>

                            <textarea
                                wire:model="cancelReason"
                                rows="3"
                                placeholder="Ej. El cliente decidió no continuar..."
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            ></textarea>

                            @error('cancelReason')
                                <div class="mt-1 text-sm text-danger-600">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>


                        <div
                            class="mt-6 flex justify-end gap-3 border-t pt-4 dark:border-gray-700"
                        >

                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="$set('cancelSessionId', null)"
                            >
                                Seguir usando
                            </x-filament::button>

                            @if(
                                $canOperateComputerRental
                                && $cancelGrace
                                && $cancelGrace['allowed']
                            )

                                <x-filament::button
                                    type="button"
                                    color="danger"
                                    wire:click="cancelRental"
                                    wire:loading.attr="disabled"
                                    wire:target="cancelRental"
                                >
                                    Cancelar sin cobro
                                </x-filament::button>

                            @endif

                        </div>

                    </div>

                </div>

            </div>

        @endif


        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

            <div class="mb-4 text-lg font-bold">
                Últimas rentas
            </div>

            <div class="overflow-x-auto">

                <table class="w-full text-sm">

                    <thead>
                        <tr class="border-b text-left dark:border-gray-700">
                            <th class="p-2">Equipo</th>
                            <th class="p-2">Inicio</th>
                            <th class="p-2">Fin</th>
                            <th class="p-2">Estado</th>
                            <th class="p-2">Min.</th>
                            <th class="p-2 text-right">Importe</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($this->recentSessions as $session)

                            <tr class="border-b dark:border-gray-800">

                                <td class="p-2">
                                    {{ $session->station?->code ?? '#' . $session->station_id }}
                                </td>

                                <td class="p-2">
                                    {{ $session->started_at?->format('d/m/Y H:i') }}
                                </td>

                                <td class="p-2">
                                    {{ $session->ended_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>

                                <td class="p-2">

                                    @php
                                        $statusLabel =
                                            $this->rentalStatusLabel(
                                                $session->status
                                            );
                                    @endphp

                                    <span
                                        @class([
                                            'rounded-full px-2 py-1 text-xs font-semibold',
                                            'bg-green-100 text-green-700' =>
                                                $session->status === 'paid',
                                            'bg-blue-100 text-blue-700' =>
                                                in_array(
                                                    $session->status,
                                                    ['active', 'sent_to_pos'],
                                                    true
                                                ),
                                            'bg-amber-100 text-amber-700' =>
                                                $session->status === 'pending_pos',
                                            'bg-red-100 text-red-700' =>
                                                in_array(
                                                    $session->status,
                                                    [
                                                        'cancelled',
                                                        'ticket_cancelled',
                                                    ],
                                                    true
                                                ),
                                            'bg-gray-100 text-gray-700' =>
                                                ! in_array(
                                                    $session->status,
                                                    [
                                                        'paid',
                                                        'active',
                                                        'sent_to_pos',
                                                        'pending_pos',
                                                        'cancelled',
                                                        'ticket_cancelled',
                                                    ],
                                                    true
                                                ),
                                        ])
                                    >
                                        {{ $statusLabel }}
                                    </span>

                                </td>

                                <td class="p-2">
                                    {{ $session->billable_minutes }}
                                </td>

                                <td class="p-2 text-right font-semibold">
                                    ${{ number_format((float) $session->amount, 2) }}
                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    {{-- CIBER3H3B_RESEND_MODAL --}}
    @if(
        $resendTicketSessionId
        && $canOperateComputerRental
    )

        @php
            $resendSession =
                $this->resendingTicketSession;

            $resendConsumptionTotal =
                $resendSession
                    ? $this->consumptionTotalForSession(
                        $resendSession->id
                    )
                    : 0;

            $resendTotal =
                $resendSession
                    ? (
                        (float)
                            $resendSession->amount
                        + $resendConsumptionTotal
                    )
                    : 0;
        @endphp

        <div
            role="dialog"
            aria-modal="true"
            style="
                position: fixed;
                inset: 0;
                z-index: 100004;
                overflow-y: auto;
            "
        >

            <button
                type="button"
                aria-label="Cerrar"
                wire:click="cancelResendCancelledTicket"
                style="
                    position: fixed;
                    inset: 0;
                    width: 100vw;
                    height: 100vh;
                    border: 0;
                    margin: 0;
                    padding: 0;
                    background: rgba(15,23,42,.72);
                    backdrop-filter: blur(2px);
                    -webkit-backdrop-filter: blur(2px);
                    z-index: 1;
                "
            ></button>


            <div
                style="
                    position: relative;
                    z-index: 2;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    pointer-events: none;
                "
            >

                <div
                    class="rounded-2xl bg-white p-6 dark:bg-gray-900"
                    style="
                        position: relative;
                        z-index: 3;
                        width: 100%;
                        max-width: 560px;
                        background: white;
                        border-radius: 18px;
                        box-shadow:
                            0 30px 80px rgba(0,0,0,.38),
                            0 8px 24px rgba(0,0,0,.22);
                        pointer-events: auto;
                    "
                >

                    <div class="text-xl font-bold">
                        Generar nuevo ticket
                    </div>

                    @if($resendSession)

                        <div class="mt-1 text-sm text-gray-500">
                            {{ $resendSession->station?->code }}
                            ·
                            {{ $resendSession->station?->name }}
                        </div>


                        <div
                            class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800"
                        >
                            Ticket anterior:
                            <strong>
                                {{
                                    $resendSession
                                        ->posOrder
                                        ?->number
                                    ?? '#'
                                        . $resendSession
                                            ->pos_order_id
                                }}
                            </strong>

                            <div class="mt-1">
                                Este ticket permanece cancelado
                                y se conservará en el historial.
                            </div>
                        </div>


                        <div
                            class="mt-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-800"
                        >

                            <div class="flex justify-between text-sm">
                                <span>
                                    Renta
                                </span>

                                <strong>
                                    ${{
                                        number_format(
                                            (float)
                                                $resendSession
                                                    ->amount,
                                            2
                                        )
                                    }}
                                </strong>
                            </div>

                            <div class="mt-2 flex justify-between text-sm">
                                <span>
                                    Consumos
                                </span>

                                <strong>
                                    ${{
                                        number_format(
                                            $resendConsumptionTotal,
                                            2
                                        )
                                    }}
                                </strong>
                            </div>

                            <div
                                class="mt-3 flex justify-between border-t pt-3 text-lg font-bold dark:border-gray-700"
                            >
                                <span>
                                    Nuevo ticket
                                </span>

                                <span>
                                    ${{
                                        number_format(
                                            $resendTotal,
                                            2
                                        )
                                    }}
                                </span>
                            </div>

                        </div>


                        <div
                            class="mt-4 rounded-xl bg-blue-50 p-4 text-sm text-blue-800"
                        >
                            Se usarán exactamente los
                            <strong>
                                {{
                                    $resendSession
                                        ->billable_minutes
                                }} minutos
                            </strong>
                            y el importe ya congelado.

                            <div class="mt-1">
                                No se recalculará el tiempo y la PC
                                no volverá a estado En uso.
                            </div>
                        </div>


                        <div
                            class="mt-6 flex justify-end gap-3 border-t pt-4 dark:border-gray-700"
                        >

                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="cancelResendCancelledTicket"
                            >
                                Cerrar
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                wire:click="resendCancelledTicket"
                                wire:loading.attr="disabled"
                                wire:target="resendCancelledTicket"
                            >
                                Generar nuevo ticket
                            </x-filament::button>

                        </div>

                    @else

                        <div
                            class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800"
                        >
                            La renta ya no está disponible
                            para regenerar.
                        </div>

                        <div class="mt-5 flex justify-end">
                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="cancelResendCancelledTicket"
                            >
                                Cerrar
                            </x-filament::button>
                        </div>

                    @endif

                </div>

            </div>

        </div>

    @endif


    {{-- CIBER3G1_FINISH_MODAL_UI --}}
    @if(
        $finishSessionId
        && $canOperateComputerRental
    )

        @php
            $finishRentalSession =
                $this->finishingSession;

            $finishEstimate =
                $finishRentalSession
                    ? $this->liveEstimate(
                        $finishRentalSession
                    )
                    : null;

            $finishConsumptionTotal =
                $finishRentalSession
                    ? $this->consumptionTotalForSession(
                        $finishRentalSession->id
                    )
                    : 0;

            $finishCurrentRentalAmount =
                (float) (
                    $finishEstimate['amount']
                    ?? 0
                );

            $finishCurrentTotal =
                $finishCurrentRentalAmount
                + $finishConsumptionTotal;
        @endphp

        <div
            role="dialog"
            aria-modal="true"
            style="
                position: fixed;
                inset: 0;
                z-index: 100002;
                overflow-y: auto;
            "
        >

            <button
                type="button"
                aria-label="Cerrar"
                wire:click="cancelFinishRental"
                style="
                    position: fixed;
                    inset: 0;
                    width: 100vw;
                    height: 100vh;
                    border: 0;
                    margin: 0;
                    padding: 0;
                    background: rgba(15,23,42,.72);
                    backdrop-filter: blur(2px);
                    -webkit-backdrop-filter: blur(2px);
                    z-index: 1;
                "
            ></button>


            <div
                style="
                    position: relative;
                    z-index: 2;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    pointer-events: none;
                "
            >

                <div
                    class="rounded-2xl bg-white p-6 dark:bg-gray-900"
                    style="
                        position: relative;
                        z-index: 3;
                        width: 100%;
                        max-width: 560px;
                        background: white;
                        border-radius: 18px;
                        box-shadow:
                            0 30px 80px rgba(0,0,0,.38),
                            0 8px 24px rgba(0,0,0,.22);
                        pointer-events: auto;
                    "
                >

                    <div class="text-xl font-bold">
                        Finalizar renta
                    </div>

                    @if($finishRentalSession)

                        <div class="mt-1 text-sm text-gray-500">
                            {{ $finishRentalSession->station?->code }}
                            ·
                            {{ $finishRentalSession->station?->name }}
                        </div>


                        <div
                            class="mt-5 rounded-xl bg-gray-50 p-4 dark:bg-gray-800"
                        >

                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span>
                                    Renta actual
                                </span>

                                <strong>
                                    ${{
                                        number_format(
                                            $finishCurrentRentalAmount,
                                            2
                                        )
                                    }}
                                </strong>
                            </div>

                            <div
                                class="mt-2 flex items-center justify-between text-sm"
                            >
                                <span>
                                    Consumos
                                </span>

                                <strong>
                                    ${{
                                        number_format(
                                            $finishConsumptionTotal,
                                            2
                                        )
                                    }}
                                </strong>
                            </div>

                            <div
                                class="mt-3 flex items-center justify-between border-t pt-3 text-lg dark:border-gray-700"
                            >
                                <strong>
                                    Total actual
                                </strong>

                                <strong>
                                    ${{
                                        number_format(
                                            $finishCurrentTotal,
                                            2
                                        )
                                    }}
                                </strong>
                            </div>

                        </div>


                        <div
                            class="mt-4 rounded-xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-950/30 dark:text-blue-200"
                        >
                            Al confirmar se cerrará la renta y
                            se creará un ticket pendiente en
                            Punto de Venta con la renta y todos
                            los consumos de esta cuenta.
                        </div>


                        <div
                            class="mt-6 flex justify-end gap-3 border-t pt-4 dark:border-gray-700"
                        >

                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="cancelFinishRental"
                            >
                                Seguir usando
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                wire:click="finishRental({{ $finishRentalSession->id }})"
                                wire:loading.attr="disabled"
                                wire:target="finishRental({{ $finishRentalSession->id }})"
                            >
                                Finalizar y enviar a PDV
                            </x-filament::button>

                        </div>

                    @else

                        <div class="mt-4 text-sm text-danger-600">
                            La renta ya no está disponible.
                        </div>

                        <div class="mt-5 flex justify-end">
                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="cancelFinishRental"
                            >
                                Cerrar
                            </x-filament::button>
                        </div>

                    @endif

                </div>

            </div>

        </div>

    @endif


    {{-- CIBER3D3_REMOVE_MODAL_UI --}}
    @if($removeConsumptionLineId)

        @php
            $removeLine = \App\Models\ComputerRentalSessionLine::query()
                ->where('company_id', \Filament\Facades\Filament::getTenant()?->id)
                ->find($removeConsumptionLineId);
        @endphp

        <div
            role="dialog"
            aria-modal="true"
            style="
                position: fixed;
                inset: 0;
                z-index: 100001;
                overflow-y: auto;
            "
        >

            <button
                type="button"
                aria-label="Cerrar"
                wire:click="cancelRemoveConsumption"
                style="
                    position: fixed;
                    inset: 0;
                    width: 100vw;
                    height: 100vh;
                    border: 0;
                    margin: 0;
                    padding: 0;
                    background: rgba(15, 23, 42, 0.72);
                    backdrop-filter: blur(2px);
                    -webkit-backdrop-filter: blur(2px);
                    z-index: 1;
                "
            ></button>

            <div
                style="
                    position: relative;
                    z-index: 2;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    pointer-events: none;
                "
            >

                <div
                    class="rounded-2xl bg-white p-6 dark:bg-gray-900"
                    style="
                        position: relative;
                        z-index: 3;
                        width: 100%;
                        max-width: 520px;
                        background: white;
                        border-radius: 18px;
                        box-shadow:
                            0 30px 80px rgba(0,0,0,.38),
                            0 8px 24px rgba(0,0,0,.22);
                        pointer-events: auto;
                    "
                >

                    <div class="flex items-start gap-4">

                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-danger-50 text-danger-600"
                        >
                            <x-heroicon-o-trash class="h-6 w-6" />
                        </div>

                        <div class="min-w-0 flex-1">

                            <div class="text-lg font-bold">
                                Quitar consumo
                            </div>

                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">

                                ¿Deseas quitar

                                <strong>
                                    {{ $removeLine?->description ?? 'este consumo' }}
                                </strong>

                                de la cuenta?

                            </div>

                            @if($removeLine)

                                <div
                                    class="mt-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-800"
                                >

                                    <div class="flex items-center justify-between gap-4">

                                        <div>

                                            <div class="font-semibold">
                                                {{ $removeLine->description }}
                                            </div>

                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ number_format((float) $removeLine->quantity, 2) }}
                                                ×
                                                ${{ number_format((float) $removeLine->unit_price, 2) }}
                                            </div>

                                        </div>

                                        <div class="font-bold">
                                            ${{ number_format((float) $removeLine->total, 2) }}
                                        </div>

                                    </div>

                                </div>

                            @endif

                        </div>

                    </div>


                    <div
                        class="mt-6 flex justify-end gap-3 border-t pt-4 dark:border-gray-700"
                    >

                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="cancelRemoveConsumption"
                        >
                            Cancelar
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="danger"
                            wire:click="confirmRemoveConsumption"
                            wire:loading.attr="disabled"
                            wire:target="confirmRemoveConsumption"
                        >
                            Quitar consumo
                        </x-filament::button>

                    </div>

                </div>

            </div>

        </div>

    @endif


    @if(
        $editingRateId
        && $canManageComputerRental
    )

        <div
            role="dialog"
            aria-modal="true"
            style="
                position: fixed;
                inset: 0;
                z-index: 100000;
                overflow-y: auto;
            "
        >

            <button
                type="button"
                aria-label="Cerrar"
                wire:click="cancelEditRate"
                style="
                    position: fixed;
                    inset: 0;
                    width: 100vw;
                    height: 100vh;
                    border: 0;
                    margin: 0;
                    padding: 0;
                    background: rgba(15, 23, 42, 0.72);
                    backdrop-filter: blur(2px);
                    -webkit-backdrop-filter: blur(2px);
                    cursor: default;
                    z-index: 1;
                "
            ></button>


            <div
                style="
                    position: relative;
                    z-index: 2;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    pointer-events: none;
                "
            >

                <div
                    class="rounded-2xl bg-white p-6 dark:bg-gray-900"
                    style="
                        position: relative;
                        z-index: 3;
                        width: 100%;
                        max-width: 850px;
                        max-height: calc(100vh - 48px);
                        overflow-y: auto;
                        background: white;
                        border-radius: 18px;
                        box-shadow:
                            0 30px 80px rgba(0,0,0,.38),
                            0 8px 24px rgba(0,0,0,.22);
                        pointer-events: auto;
                    "
                >

                    <div class="mb-5 flex items-start justify-between gap-4">

                        <div>
                            <div class="text-xl font-bold">
                                Editar tarifa
                            </div>

                            <div class="mt-1 text-sm text-gray-500">
                                Los cambios aplicarán a rentas nuevas.
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="cancelEditRate"
                            class="rounded-lg px-3 py-1 text-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            ×
                        </button>

                    </div>


                    @if($errors->any())

                        <div
                            class="mb-5 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700"
                        >
                            <div class="font-semibold">
                                Revisa los datos:
                            </div>

                            <ul class="mt-2 list-disc pl-5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>

                    @endif


                    <div class="grid gap-4 md:grid-cols-2">

                        <div class="md:col-span-2">

                            <label class="block text-sm font-medium">
                                Nombre
                            </label>

                            <input
                                type="text"
                                wire:model="editRateName"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                            >

                        </div>


                        <div>

                            <label class="block text-sm font-medium">
                                Tipo
                            </label>

                            <select
                                wire:model.live="editRateBillingMode"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                            >
                                <option value="open">
                                    Tiempo abierto
                                </option>

                                <option value="prepaid">
                                    Prepago / paquete
                                </option>
                            </select>

                        </div>


                        <div>

                            <label class="block text-sm font-medium">
                                Producto servicio para PDV
                            </label>

                            <select
                                wire:model="editRateProductId"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                            >
                                <option value="">
                                    Sin asignar
                                </option>

                                @foreach($this->serviceProducts as $product)

                                    <option value="{{ $product->id }}">
                                        {{ $product->name }}
                                    </option>

                                @endforeach
                            </select>

                        </div>


                        @if($editRateBillingMode === 'open')

                            <div>

                                <label class="block text-sm font-medium">
                                    Precio por hora
                                </label>

                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    wire:model="editRateHourlyRate"
                                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                                >

                            </div>


                            <div class="grid grid-cols-2 gap-3">

                                <div>

                                    <label class="block text-sm font-medium">
                                        Mínimo minutos
                                    </label>

                                    <input
                                        type="number"
                                        min="1"
                                        wire:model="editRateMinimumMinutes"
                                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                                    >

                                </div>


                                <div>

                                    <label class="block text-sm font-medium">
                                        Fracción
                                    </label>

                                    <input
                                        type="number"
                                        min="1"
                                        wire:model="editRateIncrementMinutes"
                                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                                    >

                                </div>

                            </div>

                        @else

                            <div>

                                <label class="block text-sm font-medium">
                                    Minutos del paquete
                                </label>

                                <input
                                    type="number"
                                    min="1"
                                    wire:model="editRatePrepaidMinutes"
                                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                                >

                            </div>


                            <div>

                                <label class="block text-sm font-medium">
                                    Precio del paquete
                                </label>

                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    wire:model="editRatePrepaidPrice"
                                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                                >

                            </div>

                        @endif


                        <div>

                            <label class="block text-sm font-medium">
                                Cancelación sin cobro
                            </label>

                            <div class="mt-1 flex items-center gap-2">

                                <input
                                    type="number"
                                    min="0"
                                    max="60"
                                    wire:model="editRateCancellationGraceMinutes"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                                >

                                <span class="whitespace-nowrap text-sm text-gray-500">
                                    min
                                </span>

                            </div>

                            <div class="mt-1 text-xs text-gray-500">
                                0 desactiva la cancelación gratuita.
                            </div>

                        </div>


                        <div>

                            <label class="block text-sm font-medium">
                                IVA
                            </label>

                            <select
                                wire:model="editRateTaxRate"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                            >
                                <option value="0">
                                    0%
                                </option>

                                <option value="0.08">
                                    8%
                                </option>

                                <option value="0.16">
                                    16%
                                </option>
                            </select>

                        </div>


                        <div class="flex items-end">

                            <label
                                class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700"
                            >

                                <input
                                    type="checkbox"
                                    wire:model="editRateIsActive"
                                    class="rounded border-gray-300"
                                >

                                <span>
                                    <span class="block text-sm font-medium">
                                        Tarifa activa
                                    </span>

                                    <span class="block text-xs text-gray-500">
                                        Las tarifas inactivas no se pueden usar para nuevas rentas.
                                    </span>
                                </span>

                            </label>

                        </div>

                    </div>


                    <div
                        class="mt-6 rounded-xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-950/30 dark:text-blue-200"
                    >
                        Una renta que ya está corriendo conserva el precio,
                        mínimo, fracción, modalidad e IVA con los que inició.
                    </div>


                    <div
                        class="mt-6 flex flex-wrap justify-end gap-3 border-t pt-4 dark:border-gray-700"
                    >

                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="cancelEditRate"
                        >
                            Cancelar
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            wire:click="saveEditRate"
                            wire:loading.attr="disabled"
                            wire:target="saveEditRate"
                        >
                            Guardar cambios
                        </x-filament::button>

                    </div>

                </div>

            </div>

        </div>

    @endif


    @if(
        $editingStationId
        && $canManageComputerRental
    )

        @php
            $editingHasActiveRental =
                $this->editingStationHasActiveRental();
        @endphp

        <div
            class="fixed inset-0 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            style="
                position: fixed;
                inset: 0;
                z-index: 99999;
            "
        >
            {{-- CIBER3E1A_MODAL_VISUAL --}}
            {{-- CIBER3E1B_INLINE_BACKDROP --}}

            <button
                type="button"
                aria-label="Cerrar"
                wire:click="cancelEditStation"
                style="
                    position: fixed;
                    inset: 0;
                    width: 100vw;
                    height: 100vh;
                    border: 0;
                    margin: 0;
                    padding: 0;
                    background: rgba(15, 23, 42, 0.72);
                    backdrop-filter: blur(2px);
                    -webkit-backdrop-filter: blur(2px);
                    cursor: default;
                    z-index: 1;
                "
            ></button>

            <div
                style="
                    position: relative;
                    z-index: 2;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    pointer-events: none;
                "
            >

            <div
                class="rounded-2xl bg-white p-6 dark:bg-gray-900"
                style="
                    position: relative;
                    z-index: 3;
                    width: 100%;
                    max-width: 850px;
                    max-height: calc(100vh - 48px);
                    overflow-y: auto;
                    background: white;
                    border-radius: 18px;
                    box-shadow:
                        0 30px 80px rgba(0, 0, 0, 0.38),
                        0 8px 24px rgba(0, 0, 0, 0.22);
                    pointer-events: auto;
                "
            >

                <div class="mb-5 flex items-start justify-between gap-4">

                    <div>
                        <div class="text-xl font-bold">
                            Editar PC
                        </div>

                        <div class="mt-1 text-sm text-gray-500">
                            Configuración de la estación de renta.
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="cancelEditStation"
                        class="rounded-lg px-3 py-1 text-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        ×
                    </button>

                </div>


                @if($editingHasActiveRental)

                    <div
                        class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        Esta PC tiene una renta activa.

                        El Punto de Venta, el estado operativo y la activación
                        quedan bloqueados hasta finalizar la renta.
                    </div>

                @endif


                @if($errors->any())

                    <div
                        class="mb-5 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950/20"
                    >
                        <div class="font-semibold">
                            Revisa los datos:
                        </div>

                        <ul class="mt-2 list-disc pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>

                @endif


                <div class="grid gap-4 md:grid-cols-2">

                    <div>
                        <label class="block text-sm font-medium">
                            Código
                        </label>

                        <input
                            type="text"
                            wire:model="editStationCode"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                        >

                        @error('editStationCode')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>


                    <div>
                        <label class="block text-sm font-medium">
                            Nombre
                        </label>

                        <input
                            type="text"
                            wire:model="editStationName"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                        >

                        @error('editStationName')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>


                    <div>
                        <label class="block text-sm font-medium">
                            Tarifa predeterminada
                        </label>

                        <select
                            wire:model="editStationDefaultRateId"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                        >
                            <option value="">
                                Sin tarifa
                            </option>

                            @foreach($this->rates as $rate)

                                <option value="{{ $rate->id }}">
                                    {{ $rate->name }}
                                </option>

                            @endforeach
                        </select>

                        @error('editStationDefaultRateId')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>


                    <div>
                        <label class="block text-sm font-medium">
                            Punto de venta
                        </label>

                        <select
                            wire:model="editStationPosPointId"
                            @disabled($editingHasActiveRental)
                            class="mt-1 w-full rounded-lg border-gray-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950"
                        >
                            <option value="">
                                Sin asignar
                            </option>

                            @foreach($this->posPoints as $posPoint)

                                <option value="{{ $posPoint->id }}">
                                    {{ $posPoint->name }}
                                </option>

                            @endforeach
                        </select>

                        @error('editStationPosPointId')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>


                    <div>
                        <label class="block text-sm font-medium">
                            Estado
                        </label>

                        <select
                            wire:model="editStationStatus"
                            @disabled($editingHasActiveRental)
                            class="mt-1 w-full rounded-lg border-gray-300 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950"
                        >
                            @if($editingHasActiveRental)
                                <option value="in_use">
                                    En uso
                                </option>
                            @else
                                <option value="available">
                                    Disponible
                                </option>

                                <option value="reserved">
                                    Reservada
                                </option>

                                <option value="maintenance">
                                    Mantenimiento
                                </option>

                                <option value="offline">
                                    Fuera de línea
                                </option>
                            @endif
                        </select>

                        @error('editStationStatus')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>


                    <div class="flex items-end">

                        <label
                            class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700"
                        >

                            <input
                                type="checkbox"
                                wire:model="editStationIsActive"
                                @disabled($editingHasActiveRental)
                                class="rounded border-gray-300"
                            >

                            <span>
                                <span class="block text-sm font-medium">
                                    Estación activa
                                </span>

                                <span class="block text-xs text-gray-500">
                                    Si está inactiva no aparecerá en el panel operativo.
                                </span>
                            </span>

                        </label>

                        @error('editStationIsActive')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium">
                            Notas
                        </label>

                        <textarea
                            wire:model="editStationNotes"
                            rows="3"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                            placeholder="Ubicación física, observaciones, equipo asignado..."
                        ></textarea>

                        @error('editStationNotes')
                            <div class="mt-1 text-xs text-danger-600">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>

                </div>


                <div
                    class="mt-6 flex flex-wrap justify-end gap-3 border-t pt-4 dark:border-gray-700"
                >

                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="cancelEditStation"
                    >
                        Cancelar
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        wire:click="saveEditStation"
                        wire:loading.attr="disabled"
                        wire:target="saveEditStation"
                    >
                        Guardar cambios
                    </x-filament::button>

                </div>

            </div>

            </div>

        </div>

    @endif

</x-filament-panels::page>
