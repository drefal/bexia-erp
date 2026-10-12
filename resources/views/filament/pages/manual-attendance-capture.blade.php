<x-filament-panels::page>
    <div class="space-y-5">

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">

            <div class="flex flex-wrap items-end gap-3">

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Fecha
                    </label>

                    <div class="flex flex-wrap items-center gap-2">

                        <button
                            type="button"
                            wire:click="previousDay"
                            class="inline-flex h-[42px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                            title="Ir al día anterior"
                        >
                            Día anterior
                        </button>

                        <input
                            type="date"
                            wire:model.live="attendanceDate"
                            max="{{ now()->toDateString() }}"
                            class="fi-input block w-[175px] rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                        >

                        <button
                            type="button"
                            wire:click="nextDay"
                            @disabled(
                                \Carbon\CarbonImmutable::parse($attendanceDate)
                                    ->greaterThanOrEqualTo(
                                        \Carbon\CarbonImmutable::today()
                                    )
                            )
                            class="inline-flex h-[42px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900"
                            title="Ir al día siguiente"
                        >
                            Día siguiente
                        </button>

                    </div>
                </div>

                @if (! $dayLocked)

                    <div class="flex flex-wrap items-center gap-2">

                        <x-filament::button
                            wire:click="markAllNormalEntry"
                            color="success"
                            icon="heroicon-o-arrow-right-on-rectangle"
                        >
                            Todos entrada normal
                        </x-filament::button>


                        <button
                            type="button"
                            wire:click="markAllNormalExit"
                            title="Asignar salida normal a todos"
                            style="
                                display: inline-flex !important;
                                align-items: center !important;
                                justify-content: center !important;
                                min-width: 180px !important;
                                height: 42px !important;
                                padding: 0 16px !important;
                                margin: 0 !important;
                                border: 1px solid #1d4ed8 !important;
                                border-radius: 8px !important;
                                background-color: #2563eb !important;
                                color: #ffffff !important;
                                font-size: 14px !important;
                                font-weight: 700 !important;
                                line-height: 1 !important;
                                opacity: 1 !important;
                                visibility: visible !important;
                                white-space: nowrap !important;
                                text-indent: 0 !important;
                                overflow: visible !important;
                            "
                        >
                            Todos salida normal
                        </button>


                        <x-filament::button
                            wire:click="completeAllNormalDay"
                            color="primary"
                            icon="heroicon-o-check-circle"
                        >
                            Completar jornada
                        </x-filament::button>

                        <x-filament::button
                            wire:click="clearSelection"
                            color="gray"
                        >
                            Limpiar no guardados
                        </x-filament::button>

                    </div>

                @endif

            </div>

            @if ($dayLocked)

                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950">

                    <div class="font-semibold text-emerald-800 dark:text-emerald-200">
                        Captura guardada y bloqueada
                    </div>

                    <div class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">
                        @if ($dayLockedByName)
                            Guardada por:
                            <strong>{{ $dayLockedByName }}</strong>
                        @endif

                        @if ($dayLockedAt)
                            · {{ $dayLockedAt }}
                        @endif
                    </div>

                    @if ($this->canUnlockDay())

                        <div class="mt-3 flex flex-wrap items-end gap-2">

                            <div class="min-w-[320px] flex-1">
                                <label class="mb-1 block text-xs font-medium">
                                    Motivo para desbloquear
                                </label>

                                <input
                                    type="text"
                                    wire:model="unlockReason"
                                    placeholder="Ej. Corrección de lista entregada por supervisor"
                                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                >
                            </div>

                            <x-filament::button
                                wire:click="unlockDay"
                                color="warning"
                                icon="heroicon-o-lock-open"
                            >
                                Desbloquear día
                            </x-filament::button>

                        </div>

                    @endif

                </div>

            @else

                <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    Captura para contingencias o listas manuales.
                    Las checadas reales permanecen protegidas.
                    Al pulsar Guardar asistencias, este día quedará
                    cerrado para modificaciones.
                </div>

            @endif

        </div>

        <div class="flex items-center justify-between px-1">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Empleados programados para esta fecha:
                <span class="font-semibold">
                    {{ count($rows) }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

            <table class="w-full min-w-[1450px] text-sm">

                <thead class="border-b bg-gray-50 text-left dark:bg-gray-800">
                    <tr>
                        <th class="p-3">Empleado</th>
                        <th class="p-3">Horario</th>
                        <th class="p-3 text-center">Entrada</th>
                        <th class="p-3 text-center">Entrada retardo</th>
                        <th class="p-3 text-center">Salida antes</th>
                        <th class="p-3 text-center">Salida</th>
                        <th class="p-3 text-center">Asistencia</th>
                        <th class="p-3 text-center">Falta</th>
                        <th class="p-3">Resultado</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($rows as $employeeId => $row)

                        <tr
                            wire:key="attendance-row-{{ $employeeId }}"
                            class="border-b last:border-b-0 dark:border-gray-800"
                        >

                            <td class="p-3 align-top">
                                <div class="font-medium">
                                    {{ $row['employee_name'] }}
                                </div>

                                @if (! empty($row['employee_number']))
                                    <div class="text-xs text-gray-500">
                                        {{ $row['employee_number'] }}
                                    </div>
                                @endif

                                @if (($dayLocked || (bool) ($row['protected'] ?? false)))
                                    <div class="mt-1 inline-flex rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">
                                        Checada real protegida
                                    </div>
                                @elseif (! empty($row['existing_id']))
                                    <div class="mt-1 text-xs text-sky-600">
                                        Registro manual existente
                                    </div>
                                @endif
                            </td>

                            <td class="p-3 align-top">
                                <div>
                                    {{ $row['schedule_name'] }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    @if (
                                        $row['expected_start']
                                        && $row['expected_end']
                                    )
                                        {{ $row['expected_start'] }}
                                        -
                                        {{ $row['expected_end'] }}
                                    @else
                                        Horario sin horas fijas
                                    @endif
                                </div>
                            </td>

                            <td class="p-3 text-center align-top">

                                @if (
                                    ($dayLocked || (bool) ($row['protected'] ?? false))
                                )
                                    <span class="text-xs text-gray-400">
                                        Protegida
                                    </span>

                                @elseif (! empty($row['expected_start']))

                                    <button
                                        type="button"
                                        wire:click="setNormalEntry({{ $employeeId }})"
                                        class="inline-flex min-w-[64px] items-center justify-center rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-green-500"
                                    >
                                        {{ $row['expected_start'] }}
                                    </button>

                                @else
                                    <span class="text-xs text-gray-400">
                                        Sin hora
                                    </span>
                                @endif

                            </td>

                            <td class="p-3 align-top">

                                @if (
                                    ($dayLocked || (bool) ($row['protected'] ?? false))
                                )
                                    <div class="text-center text-xs text-gray-400">
                                        Protegida
                                    </div>

                                @else
                                    <div class="flex min-w-[180px] items-center gap-2">

                                        <input
                                            type="time"
                                            wire:model="rows.{{ $employeeId }}.late_time"
                                            class="fi-input block w-28 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                        >

                                        <x-filament::button
                                            size="sm"
                                            color="warning"
                                            wire:click="applyLateEntry({{ $employeeId }})"
                                        >
                                            Retardo
                                        </x-filament::button>

                                    </div>
                                @endif

                            </td>

                            <td class="p-3 align-top">

                                @if (
                                    ($dayLocked || (bool) ($row['protected'] ?? false))
                                )
                                    <div class="text-center text-xs text-gray-400">
                                        Protegida
                                    </div>

                                @else
                                    <div class="flex min-w-[180px] items-center gap-2">

                                        <input
                                            type="time"
                                            wire:model="rows.{{ $employeeId }}.early_out_time"
                                            class="fi-input block w-28 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                                        >

                                        <x-filament::button
                                            size="sm"
                                            color="warning"
                                            wire:click="applyEarlyExit({{ $employeeId }})"
                                        >
                                            Antes
                                        </x-filament::button>

                                    </div>
                                @endif

                            </td>

                            <td class="p-3 text-center align-top">

                                @if (
                                    ($dayLocked || (bool) ($row['protected'] ?? false))
                                )
                                    <span class="text-xs text-gray-400">
                                        Protegida
                                    </span>

                                @elseif (! empty($row['expected_end']))

                                    <button
                                        type="button"
                                        wire:click="setNormalExit({{ $employeeId }})"
                                        class="inline-flex min-w-[64px] items-center justify-center rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-green-500"
                                    >
                                        {{ $row['expected_end'] }}
                                    </button>

                                @else
                                    <span class="text-xs text-gray-400">
                                        Sin hora
                                    </span>
                                @endif

                            </td>

                            <td class="p-3 text-center align-top">

                                @if (
                                    ($dayLocked || (bool) ($row['protected'] ?? false))
                                )
                                    <span class="text-xs text-gray-400">
                                        Protegida
                                    </span>

                                @elseif (
                                    ! empty($row['expected_start'])
                                    && ! empty($row['expected_end'])
                                )

                                    <button
                                        type="button"
                                        wire:click="markCorrectAttendance({{ $employeeId }})"
                                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-3 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-green-500"
                                    >
                                        Asistencia
                                    </button>

                                @else
                                    <span class="text-xs text-gray-400">
                                        Manual
                                    </span>
                                @endif

                            </td>

                            <td class="p-3 text-center align-top">

                                @if (
                                    ($dayLocked || (bool) ($row['protected'] ?? false))
                                )
                                    <span class="text-xs text-gray-400">
                                        Protegida
                                    </span>
                                @else

                                    <x-filament::button
                                        size="sm"
                                        color="danger"
                                        wire:click="markAbsence({{ $employeeId }})"
                                    >
                                        Falta
                                    </x-filament::button>

                                @endif

                            </td>

                            <td class="p-3 align-top">

                                @if (
                                    ($dayLocked || (bool) ($row['protected'] ?? false))
                                )

                                    <div class="font-medium text-emerald-700">
                                        Checada real
                                    </div>

                                    <div class="text-xs text-gray-500">
                                        {{ $row['existing_source'] ?: 'sistema' }}
                                    </div>

                                @elseif (
                                    ($row['mode'] ?? 'skip')
                                    === 'absence'
                                )

                                    <div class="font-semibold text-red-600">
                                        Falta
                                    </div>

                                @elseif (
                                    ($row['mode'] ?? 'skip')
                                    === 'present'
                                )

                                    <div class="font-medium text-green-700">
                                        @if (
                                            $row['clock_in']
                                                === $row['expected_start']
                                            && $row['clock_out']
                                                === $row['expected_end']
                                        )
                                            Asistencia correcta
                                        @else
                                            Presente
                                        @endif
                                    </div>

                                    <div class="mt-1 text-xs">
                                        Entrada:
                                        <strong>
                                            {{ $row['clock_in'] ?: '—' }}
                                        </strong>
                                    </div>

                                    <div class="text-xs">
                                        Salida:
                                        <strong>
                                            {{ $row['clock_out'] ?: '—' }}
                                        </strong>
                                    </div>

                                @else

                                    <div class="text-gray-500">
                                        Sin registrar
                                    </div>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="9"
                                class="p-8 text-center text-gray-500"
                            >
                                No hay empleados con jornada programada
                                para esta fecha.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if (! $dayLocked)

            <div class="flex justify-end">

                <x-filament::button
                    size="lg"
                    color="success"
                    icon="heroicon-o-lock-closed"
                    wire:click="saveAttendances"
                    wire:loading.attr="disabled"
                >
                    Guardar y bloquear asistencias
                </x-filament::button>

            </div>

        @endif

    </div>
</x-filament-panels::page>
