<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $rows = $this->rows();
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
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Empleado</label>
                    <select wire:model.live="employee_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="">Todos</option>
                        @foreach ($this->employeeOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
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
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="clearFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                    Limpiar filtros
                </button>
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
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
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
                            <th class="px-4 py-3 text-right font-semibold">Retardo</th>
                            <th class="px-4 py-3 text-right font-semibold">Extra</th>
                            <th class="px-4 py-3 text-center font-semibold">Evidencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr>
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
                                <td class="px-4 py-3 text-right">{{ (int) $row->late_minutes }} min</td>
                                <td class="px-4 py-3 text-right">{{ (int) $row->overtime_minutes }} min</td>

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
                                <td colspan="13" class="px-4 py-8 text-center text-gray-500">
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
