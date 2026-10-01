<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $rows = $this->rows();
        $employeeSearchOptions =
            $this->employeeSearchOptions();
        $selectedEmployees =
            $this->selectedEmployeeOptions();
    @endphp

    <div
        class="space-y-6"
        x-data="{
            photoOpen: false,
            photoUrl: '',
            photoTitle: '',
            openPhoto(url, title) {
                this.photoUrl = url;
                this.photoTitle = title;
                this.photoOpen = true;
            },
            closePhoto() {
                this.photoOpen = false;
                this.photoUrl = '';
                this.photoTitle = '';
            }
        }"
        x-on:keydown.escape.window="closePhoto()"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-5">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Desde</label>
                    <input type="date" wire:model.live="from" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Hasta</label>
                    <input type="date" wire:model.live="to" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Departamento</label>
                    <select wire:model.live="department_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="">Todos</option>
                        @foreach ($this->departmentOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Estado</label>
                    <select wire:model.live="status" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="">Todos</option>
                        @foreach ($this->statusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- BEXIA_V5836J2AR31C_KEY_PERSONNEL_FILTER --}}
                <div
                    x-data="{
                        keyOnly: $wire.entangle('only_key_personnel').live
                    }"
                >
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        Personal Clave
                    </label>

                    <div class="mt-1 flex h-[42px] items-center">
                        <button
                            type="button"
                            x-on:click="keyOnly = ! keyOnly"
                            class="inline-flex items-center gap-3 text-left"
                            x-bind:aria-pressed="keyOnly ? 'true' : 'false'"
                        >
                        <span
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-all duration-200"
                            x-bind:style="
                                keyOnly
                                    ? 'background-color:#2563eb;'
                                    : 'background-color:#d1d5db;'
                            "
                        >
                            <span
                                class="absolute top-1 h-4 w-4 rounded-full bg-white shadow transition-all duration-200"
                                x-bind:style="
                                    keyOnly
                                        ? 'transform:translateX(24px); left:0;'
                                        : 'transform:translateX(4px); left:0;'
                                "
                            ></span>
                        </span>

                        <span class="min-w-0">
                            <span
                                class="block truncate text-sm font-medium text-gray-800 dark:text-gray-100"
                            >
                                Solo Personal Clave
                            </span>
                        </span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- BEXIA_V5836J2AR31_MULTI_EMPLOYEE_FILTER --}}
            <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-end">
                <div class="min-w-0 flex-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Buscar empleados
                    </label>

                    <div class="relative mt-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="employee_search"
                            placeholder="Escribe nombre o numero de empleado..."
                            autocomplete="off"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800"
                        >

                        @if (
                            mb_strlen(trim($employee_search ?? '')) >= 2
                            && count($employeeSearchOptions) > 0
                        )
                            <div
                                class="absolute left-0 right-0 mt-2 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                                style="z-index: 100;"
                            >
                                @foreach ($employeeSearchOptions as $employee)
                                    <button
                                        type="button"
                                        wire:click="addEmployee({{ (int) $employee['id'] }})"
                                        class="block w-full border-b border-gray-100 px-4 py-3 text-left hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                                    >
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $employee['name'] }}
                                        </div>

                                        <div class="mt-0.5 text-xs text-gray-500">
                                            @if ($employee['employee_number'])
                                                No. {{ $employee['employee_number'] }}
                                            @endif

                                            @if (
                                                $employee['employee_number']
                                                && $employee['department_name']
                                            )
                                                ·
                                            @endif

                                            @if ($employee['department_name'])
                                                {{ $employee['department_name'] }}
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (
                            mb_strlen(trim($employee_search ?? '')) >= 2
                            && count($employeeSearchOptions) === 0
                        )
                            <div
                                class="absolute left-0 right-0 mt-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                                style="z-index: 100;"
                            >
                                No hay empleados que coincidan.
                            </div>
                        @endif
                    </div>

                    @if (count($selectedEmployees) > 0)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($selectedEmployees as $employee)
                                <button
                                    type="button"
                                    wire:click="removeEmployee({{ (int) $employee['id'] }})"
                                    class="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-300"
                                    title="Quitar {{ $employee['name'] }}"
                                >
                                    <span>
                                        {{ $employee['name'] }}

                                        @if ($employee['department_name'])
                                            · {{ $employee['department_name'] }}
                                        @endif
                                    </span>

                                    <span
                                        aria-hidden="true"
                                        class="text-base leading-none"
                                    >
                                        ×
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-2 text-xs text-gray-500">
                            {{ count($selectedEmployees) }}
                            {{ count($selectedEmployees) === 1 ? 'empleado seleccionado' : 'empleados seleccionados' }}.
                            Haz clic en una etiqueta para quitarla.
                        </div>
                    @else
                        <div class="mt-2 text-xs text-gray-500">
                            Sin empleados seleccionados se muestran todos.
                            Puedes agregar empleados de distintas areas.
                        </div>
                    @endif
                </div>

                <div class="shrink-0">
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4 xl:grid-cols-8">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Registros</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['records'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Empleados</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['employees'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Horas trabajadas</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['worked_hours'], 2) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Retardos</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['late_count'] }}</div>
                <div class="text-xs text-gray-500">{{ $summary['late_minutes'] }} min</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Faltas</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['absence_count'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Salidas temp.</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['early_leave_count'] }}</div>
                <div class="text-xs text-gray-500">{{ $summary['early_leave_minutes'] }} min</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Horas extra</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['overtime_hours'], 2) }}</div>
                <div class="text-xs text-gray-500">{{ $summary['overtime_minutes'] }} min</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs uppercase text-gray-500">Desc. trabajados</div>
                <div class="mt-1 text-2xl font-semibold">{{ $summary['rest_day_worked_count'] }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="text-lg font-semibold">Detalle de asistencias</div>
                <div class="mt-1 text-sm text-gray-500">
                    Periodo {{ $this->dateOnly($from) }} - {{ $this->dateOnly($to) }} · {{ $rows->count() }} registros
                </div>
            </div>

            <div class="overflow-x-auto">
@php
    $attendanceIncidentMap =
        \App\Support\EmployeeAttendanceReportService::incidentSummaryByAttendance(
            $rows
        );
@endphp

                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        
                        <tr class="border-b border-gray-200 bg-gray-100/80 text-[11px] uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-400">
                            <th colspan="11" class="px-4 py-2 text-left font-semibold">
                                Datos de asistencia
                            </th>

                            <th colspan="6" class="border-l border-gray-200 px-4 py-2 text-center font-semibold dark:border-gray-700">
                                Incidencias
                            </th>

                            <th colspan="4" class="border-l border-gray-200 px-4 py-2 text-center font-semibold dark:border-gray-700">
                                Resolución
                            </th>

                            <th colspan="1" class="border-l border-gray-200 px-4 py-2 text-center font-semibold dark:border-gray-700">
                                Evidencia
                            </th>
                        </tr>
<tr>
                            <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                            <th class="px-4 py-3 text-left font-semibold">Empleado</th>
                            <th class="px-4 py-3 text-left font-semibold">Departamento</th>
                            <th class="px-4 py-3 text-left font-semibold">Puesto</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado</th>
                            <th class="px-4 py-3 text-left font-semibold">Entrada</th>
                            <th class="px-4 py-3 text-left font-semibold">Salida comida</th>
                            <th class="px-4 py-3 text-left font-semibold">Regreso comida</th>
                            <th class="px-4 py-3 text-left font-semibold">Salida</th>
                            <th class="px-4 py-3 text-right font-semibold">Horas</th>
                            <th class="px-4 py-3 text-right font-semibold">Extra</th>
                            <th class="px-4 py-3 text-right font-semibold">Retardo</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Comida excedida</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Salida antes</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Falta</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Jornada incompleta</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Marcaje incompleto</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Total</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Aprobadas</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Rechazadas</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Pendientes</th>
                            <th class="px-4 py-3 text-center font-semibold">Evidencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr>
                                @php
                                    $incident =
                                        $attendanceIncidentMap[
                                            (int) $row->id
                                        ]
                                        ?? [
                                            'retardo_label' => '—',
                                            'comida_excedida_label' => '—',
                                            'salida_antes_label' => '—',
                                            'falta_label' => '—',
                                            'jornada_incompleta_label' => '—',
                                            'marcaje_incompleto' => '—',
                                            'total' => 0,
                                            'approved' => 0,
                                            'rejected' => 0,
                                            'pending' => 0,
                                        ];
                                @endphp

                                <td class="px-4 py-3">{{ $this->dateOnly($row->attendance_date) }}</td>
                                <td class="px-4 py-3">
                                    <a
                                        href="{{ $this->employeeEditUrl((int) $row->employee_id) }}"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        title="Abrir ficha de {{ $row->employee_name ?: 'empleado' }}"
                                    >
                                        {{ $row->employee_name ?: '-' }}
                                    </a>
                                    <div class="text-xs text-gray-500">{{ $row->employee_number ?: '' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $row->department_name ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $row->position_name ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $this->statusLabel($row->status) }}</td>
                                <td class="px-4 py-3">
                                    <div>{{ $this->timeOnly($row->clock_in_at) }}</div>
                                    <div class="text-xs text-gray-500">Esp. {{ $this->timeOnly($row->expected_start_at) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $this->timeOnly($row->meal_out_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $this->timeOnly($row->meal_in_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ $this->timeOnly($row->clock_out_at) }}</div>
                                    <div class="text-xs text-gray-500">Esp. {{ $this->timeOnly($row->expected_end_at) }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->worked_hours, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ (int) $row->overtime_minutes }} min</td>
                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if($incident['retardo_label'] !== '—')
                                        <span class="inline-flex rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {{ $incident['retardo_label'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if($incident['comida_excedida_label'] !== '—')
                                        <span class="inline-flex rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {{ $incident['comida_excedida_label'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if($incident['salida_antes_label'] !== '—')
                                        <span class="inline-flex rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {{ $incident['salida_antes_label'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if($incident['falta_label'] !== '—')
                                        <span class="inline-flex rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {{ $incident['falta_label'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if($incident['jornada_incompleta_label'] !== '—')
                                        <span class="inline-flex rounded-lg bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                            {{ $incident['jornada_incompleta_label'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    @if($incident['marcaje_incompleto'] !== '—')
                                        <span class="inline-flex rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {{ $incident['marcaje_incompleto'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex min-w-8 justify-center rounded-lg bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        {{ $incident['total'] }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex min-w-8 justify-center rounded-lg bg-green-50 px-2 py-1 text-xs font-semibold text-green-700 dark:bg-green-950 dark:text-green-300">
                                        {{ $incident['approved'] }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex min-w-8 justify-center rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">
                                        {{ $incident['rejected'] }}
                                    </span>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex min-w-8 justify-center rounded-lg px-2 py-1 text-xs font-semibold
                                        {{ $incident['pending'] > 0
                                            ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                            : 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300' }}">
                                        {{ $incident['pending'] }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if ($row->clock_in_photo_path)
                                            <button
                                                type="button"
                                                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                                                x-on:click='openPhoto(
                                                    @js($this->attendancePhotoUrl((int) $row->id, "in")),
                                                    @js("Entrada · " . ($row->employee_name ?: "Empleado") . " · " . $this->dateOnly($row->attendance_date) . " " . $this->timeOnly($row->clock_in_at))
                                                )'
                                            >
                                                Entrada
                                            </button>
                                        @endif

                                        @if ($row->meal_out_photo_path)
                                            <button
                                                type="button"
                                                class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500"
                                                x-on:click='openPhoto(
                                                    @js($this->attendancePhotoUrl((int) $row->id, "meal_out")),
                                                    @js("Salida a comida · " . ($row->employee_name ?: "Empleado") . " · " . $this->dateOnly($row->attendance_date) . " " . $this->timeOnly($row->meal_out_at))
                                                )'
                                            >
                                                Salida comida
                                            </button>
                                        @endif

                                        @if ($row->meal_in_photo_path)
                                            <button
                                                type="button"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500"
                                                x-on:click='openPhoto(
                                                    @js($this->attendancePhotoUrl((int) $row->id, "meal_in")),
                                                    @js("Regreso de comida · " . ($row->employee_name ?: "Empleado") . " · " . $this->dateOnly($row->attendance_date) . " " . $this->timeOnly($row->meal_in_at))
                                                )'
                                            >
                                                Regreso comida
                                            </button>
                                        @endif

                                        @if ($row->clock_out_photo_path)
                                            <button
                                                type="button"
                                                class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-600"
                                                x-on:click='openPhoto(
                                                    @js($this->attendancePhotoUrl((int) $row->id, "out")),
                                                    @js("Salida · " . ($row->employee_name ?: "Empleado") . " · " . $this->dateOnly($row->attendance_date) . " " . $this->timeOnly($row->clock_out_at))
                                                )'
                                            >
                                                Salida
                                            </button>
                                        @endif

                                        @if (! $row->clock_in_photo_path && ! $row->meal_out_photo_path && ! $row->meal_in_photo_path && ! $row->clock_out_photo_path)
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="22" class="px-4 py-8 text-center text-gray-500">
                                    No hay asistencias para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div
            x-cloak
            x-show="photoOpen"
            x-transition.opacity
            class="fixed inset-0 z-[150] flex items-center justify-center bg-black/70 p-4"
            x-on:click.self="closePhoto()"
        >
            <div class="relative max-h-[92vh] w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div
                        class="pr-4 text-sm font-semibold text-gray-950 dark:text-white"
                        x-text="photoTitle"
                    ></div>

                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        x-on:click="closePhoto()"
                    >
                        Cerrar
                    </button>
                </div>

                <div class="flex max-h-[80vh] items-center justify-center overflow-auto bg-gray-100 p-4 dark:bg-black">
                    <img
                        x-bind:src="photoUrl"
                        x-bind:alt="photoTitle"
                        class="max-h-[75vh] max-w-full rounded-lg object-contain shadow"
                    >
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
