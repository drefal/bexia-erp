<x-filament-panels::page>
    <div class="space-y-6">
        @if (! $this->employee)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                <div class="text-lg font-semibold">No tienes empleado ligado</div>
                <p class="mt-1 text-sm">Pide a RRHH o al administrador que vincule tu usuario con un empleado activo.</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Empleado</div>
                    <div class="mt-1 text-xl font-semibold">{{ $this->employee->name }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ $this->employee->employee_number ?: 'Sin número de empleado' }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Hoy</div>
                    <div class="mt-1 text-xl font-semibold">{{ \Carbon\Carbon::parse($this->todayDate)->format('d/m/Y') }}</div>
                    <div class="mt-1 text-sm text-gray-500">Hora del sistema: {{ now()->format('H:i:s') }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm text-gray-500">Horario</div>
                    <div class="mt-1 text-xl font-semibold">{{ $this->scheduleName() }}</div>
                    <div class="mt-1 text-sm text-gray-500">
                        @if ($this->todayIsWorkingDay())
                            {{ $this->expectedStartLabel() }} - {{ $this->expectedEndLabel() }}
                        @else
                            Día de descanso / sin jornada
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-lg font-semibold">Registro de hoy</div>
                        <div class="mt-1 text-sm text-gray-500">
                            Usa los botones superiores para registrar entrada o salida. El estado se calcula automáticamente contra el horario operativo.
                        </div>
                    </div>

                    <div>
                        @php
                            $status = $this->todayAttendance?->status;
                            $label = $this->statusLabel($status);
                        @endphp

                        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                            {{ $label }}
                        </span>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-4">
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Entrada esperada</div>
                        <div class="mt-1 text-lg font-semibold">{{ $this->expectedStartLabel() }}</div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Entrada real</div>
                        <div class="mt-1 text-lg font-semibold">
                            {{ $this->todayAttendance?->clock_in_at?->format('H:i') ?: '-' }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Salida esperada</div>
                        <div class="mt-1 text-lg font-semibold">{{ $this->expectedEndLabel() }}</div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Salida real</div>
                        <div class="mt-1 text-lg font-semibold">
                            {{ $this->todayAttendance?->clock_out_at?->format('H:i') ?: '-' }}
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-4">
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Horas trabajadas</div>
                        <div class="mt-1 text-lg font-semibold">{{ $this->todayAttendance?->worked_hours ?: '0.00' }}</div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Retardo</div>
                        <div class="mt-1 text-lg font-semibold">{{ $this->todayAttendance?->late_minutes ?: 0 }} min</div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Salida temprana</div>
                        <div class="mt-1 text-lg font-semibold">{{ $this->todayAttendance?->early_leave_minutes ?: 0 }} min</div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Horas extra</div>
                        <div class="mt-1 text-lg font-semibold">{{ $this->todayAttendance?->overtime_minutes ?: 0 }} min</div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="text-lg font-semibold">Últimas asistencias</div>
                </div>

                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado</th>
                            <th class="px-4 py-3 text-left font-semibold">Entrada</th>
                            <th class="px-4 py-3 text-left font-semibold">Salida</th>
                            <th class="px-4 py-3 text-left font-semibold">Horas</th>
                            <th class="px-4 py-3 text-left font-semibold">Retardo</th>
                            <th class="px-4 py-3 text-left font-semibold">Extra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->latestAttendances() as $attendance)
                            <tr>
                                <td class="px-4 py-3">{{ $attendance->attendance_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $this->statusLabel($attendance->status) }}</td>
                                <td class="px-4 py-3">{{ $attendance->clock_in_at?->format('H:i') ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $attendance->clock_out_at?->format('H:i') ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $attendance->worked_hours }}</td>
                                <td class="px-4 py-3">{{ $attendance->late_minutes }} min</td>
                                <td class="px-4 py-3">{{ $attendance->overtime_minutes }} min</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                    Todavía no hay asistencias registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
