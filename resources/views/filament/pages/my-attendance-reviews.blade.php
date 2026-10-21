<x-filament-panels::page>
    @php
        $reviews = $this->reviewCards();
        $statusLabels = [
            'pending' => 'Pendiente',
            'approved' => 'Con visto bueno',
            'returned' => 'Devuelta',
        ];
        $attendanceLabels = [
            'present' => 'Asistencia',
            'late' => 'Retardo',
            'early_leave' => 'Salida anticipada',
            'late_early_leave' => 'Retardo y salida anticipada',
            'absent' => 'Falta',
            'incomplete' => 'Marcaje incompleto',
            'day_off' => 'Descanso',
            'off' => 'Descanso',
            'leave' => 'Permiso',
            'on_leave' => 'Permiso',
            'holiday' => 'Dia festivo',
        ];
        $incidentLabels = [
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
            'draft' => 'Borrador',
        ];
    @endphp

    @if($decisionModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900"
                role="dialog" aria-modal="true"
                aria-label="Confirmar decisión de asistencia">
                <h3 class="text-lg font-bold">
                    {{ $decisionType === 'approved' ? 'Confirmar visto bueno' : 'Devolver con observaciones' }}
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Revisión #{{ $decisionReviewId }}.
                    La decisión aplicará a todos los empleados asignados
                    a esta revisión.
                </p>

                @if($decisionType === 'returned')
                    <label for="review-decision-comment"
                        class="mt-4 block text-sm font-semibold">
                        Motivo de devolución (obligatorio)
                    </label>
                    <textarea id="review-decision-comment"
                        wire:model="decisionComment"
                        maxlength="2000"
                        rows="4"
                        class="mt-2 w-full rounded-lg border border-gray-300 p-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        placeholder="Describe qué debe revisar o corregir RRHH..."></textarea>
                @endif

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button"
                        wire:click="closeDecision"
                        class="rounded-lg border px-4 py-2 text-sm">
                        Cancelar
                    </button>
                    <button type="button"
                        wire:click="confirmDecision"
                        wire:loading.attr="disabled"
                        wire:target="confirmDecision"
                        style="background-color: #15803d; color: #ffffff;"
                        class="rounded-lg px-4 py-2 text-sm font-semibold">
                        {{ $decisionType === 'approved' ? 'Confirmar visto bueno' : 'Confirmar devolución' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-bold">Revisiones asignadas</h2>
            <p class="mt-1 text-sm text-gray-500">
                Aquí aparecen exclusivamente las revisiones de asistencia
                asignadas a tu usuario en esta empresa.
            </p>
        </div>

        @forelse($reviews as $review)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-bold">
                            {{ $review['payroll_name'] }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ $review['period_start'] }}
                            a
                            {{ $review['period_end'] }}
                        </p>
                    </div>
                    <span class="rounded-lg border border-gray-300 px-3 py-1 text-sm font-semibold dark:border-gray-600">
                        {{ $statusLabels[$review['status']] ?? $review['status'] }}
                    </span>
                </div>

                <div class="mt-3 grid gap-2 text-sm md:grid-cols-3">
                    <div>
                        <span class="text-gray-500">Revisión:</span>
                        #{{ $review['review_id'] }}
                    </div>
                    <div>
                        <span class="text-gray-500">Enviada:</span>
                        {{ $review['sent_at'] ?? 'Sin registro' }}
                    </div>
                    <div>
                        <span class="text-gray-500">Respondida:</span>
                        {{ $review['reviewed_at'] ?? 'Pendiente' }}
                    </div>
                </div>

                <div class="mt-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-800"
                     x-data="{ query: '', issuesOnly: false }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="text-sm font-semibold">
                            Empleados asignados
                            ({{ count($review['employees']) }})
                        </h4>
                        <span class="text-xs text-gray-500">
                            Selecciona un empleado para abrir su detalle
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <input type="search"
                            x-model.debounce.150ms="query"
                            placeholder="Buscar empleado..."
                            aria-label="Buscar empleado"
                            class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white" />

                        <label class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-xs dark:border-gray-600">
                            <input type="checkbox"
                                x-model="issuesOnly"
                                class="rounded" />
                            Solo con incidencias o alertas
                        </label>
                    </div>

                    <div class="mt-3 space-y-2">
                        @foreach($review['employees'] as $employee)
                            @php
                                $lateCount = collect($employee['attendance_rows'])
                                    ->filter(fn ($a) => (int) $a['late'] > 0)
                                    ->count();
                                $earlyCount = collect($employee['attendance_rows'])
                                    ->filter(fn ($a) => (int) $a['early'] > 0)
                                    ->count();
                                $incidentCount = count($employee['incident_rows']);
                                $hasAlerts = ($lateCount + $earlyCount + $incidentCount) > 0;
                            @endphp

                            <div
                                data-name="{{ mb_strtolower($employee['name']) }}"
                                data-alert="{{ $hasAlerts ? '1' : '0' }}"
                                x-show="$el.dataset.name.includes(query.toLocaleLowerCase()) &amp;&amp; (!issuesOnly || $el.dataset.alert === '1')">
                                <details class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                    <summary class="cursor-pointer p-3">
                                        <div class="inline-flex flex-wrap items-center gap-2">
                                            <strong>{{ $employee['name'] }}</strong>
                                            <span class="text-xs text-gray-500">
                                                #{{ $employee['id'] }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ count($employee['attendance_rows']) }}
                                            registros de asistencia
                                            · {{ $lateCount }} retardos
                                            · {{ $earlyCount }} salidas anticipadas
                                            · {{ $incidentCount }} incidencias
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            Ver detalle de marcajes e incidencias
                                        </div>
                                    </summary>

                                    <div class="border-t border-gray-200 p-3 dark:border-gray-700">
                                        <p class="mb-2 text-xs text-gray-500">
                                            Fotografia de asistencia validada al consultar.
                                            Los datos se muestran tal como fueron registrados.
                                        </p>

                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs">
                                                <thead>
                                                    <tr class="border-b dark:border-gray-700">
                                                        <th class="p-2">Fecha</th>
                                                        <th class="p-2">Estado</th>
                                                        <th class="p-2">Entrada</th>
                                                        <th class="p-2">Salida</th>
                                                        <th class="p-2">Inicio esperado</th>
                                                        <th class="p-2">Fin esperado</th>
                                                        <th class="p-2">Retardo (min)</th>
                                                        <th class="p-2">Salida anticipada (min)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($employee['attendance_rows'] as $attendance)
                                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                                            <td class="p-2">{{ $attendance['date'] }}</td>
                                                            <td class="p-2">
                                                                {{ $attendanceLabels[$attendance['status']] ?? $attendance['status'] }}
                                                            </td>
                                                            <td class="p-2">{{ $attendance['clock_in'] ? substr((string) $attendance['clock_in'], 11, 5) : '-' }}</td>
                                                            <td class="p-2">{{ $attendance['clock_out'] ? substr((string) $attendance['clock_out'], 11, 5) : '-' }}</td>
                                                            <td class="p-2">{{ $attendance['expected_in'] ? substr((string) $attendance['expected_in'], 11, 5) : '-' }}</td>
                                                            <td class="p-2">{{ $attendance['expected_out'] ? substr((string) $attendance['expected_out'], 11, 5) : '-' }}</td>
                                                            <td class="p-2">{{ $attendance['late'] }}</td>
                                                            <td class="p-2">{{ $attendance['early'] }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="8" class="p-3 text-gray-500">
                                                                Sin marcajes registrados.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>

                                        @if(count($employee['incident_rows']) > 0)
                                            <div class="mt-4">
                                                <h5 class="text-sm font-semibold">Incidencias del periodo</h5>
                                                <div class="mt-2 space-y-1 text-xs">
                                                    @foreach($employee['incident_rows'] as $incident)
                                                        <div>
                                                            {{ $incident['name'] }}
                                                            · {{ $incident['from'] }}
                                                            a {{ $incident['to'] }}
                                                            · Estado:
                                                            {{ $incidentLabels[$incident['status']] ?? $incident['status'] }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if(filled($review['comment']))
                    <div class="mt-3 rounded-lg border border-amber-300 p-3 text-sm">
                        <strong>Observaciones</strong>
                        <p class="mt-1 whitespace-pre-wrap">
                            {{ $review['comment'] }}
                        </p>
                    </div>
                @endif

                @if($review['can_decide'])
                    <div class="mt-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                            La decisión se aplicará a todos los empleados
                            de esta revisión, no individualmente.
                        </p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button"
                                wire:click="openDecision({{ $review['review_id'] }}, 'returned')"
                                class="rounded-lg border border-amber-600 px-4 py-2 text-sm font-semibold text-amber-700 dark:text-amber-300">
                                Devolver con observaciones
                            </button>
                            <button type="button"
                                wire:click="openDecision({{ $review['review_id'] }}, 'approved')"
                                style="background-color: #15803d; color: #ffffff;"
                                class="rounded-lg px-4 py-2 text-sm font-semibold">
                                Dar visto bueno
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm dark:border-gray-700 dark:bg-gray-900">
                No tienes revisiones de asistencia asignadas
                en esta empresa.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
