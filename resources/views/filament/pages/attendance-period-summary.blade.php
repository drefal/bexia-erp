<x-filament-panels::page>
    @php
    $data = $this->matrix();
@endphp

    <div class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="text-sm font-semibold">Nómina existente</label>
                    <select wire:model.live="payrollRunId"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                        <option value="">Periodo personalizado</option>
                        @foreach($this->payrollRuns() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold">Grupo de nómina</label>
                    <select wire:model.live="payrollGroupId"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                        <option value="">Todos los empleados</option>
                        @foreach($this->payrollGroups() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold">Desde</label>
                    <input type="date" wire:model.live="from"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                </div>

                <div>
                    <label class="text-sm font-semibold">Hasta</label>
                    <input type="date" wire:model.live="to"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                </div>
            </div>
        </div>

        @php
            $reviewOverview = $this->attendanceReviewOverview();
        @endphp

        @php
            $reviewStatusLabels = [
        'not_created' => 'No creado',
        'draft' => 'Borrador',
        'ready' => 'Listo',
        'refresh_required' => 'Requiere actualización',
        'not_draft' => 'Ya no está en borrador',
        'sent' => 'Enviado',
        'pending' => 'Pendiente',
        'approved' => 'Aprobado',
        'returned' => 'Devuelto'
            ];
        @endphp

        @if($reviewOverview)
            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3">
                    <h3 class="text-base font-bold">
                        Seguimiento de vistos buenos
                    </h3>
                    <p class="text-xs text-gray-500">
                        Consulta informativa. No aprueba asistencias
                        ni modifica la prenomina.
                    </p>
                </div>

                @if($reviewOverview['filter_mismatch'])
                    <div class="rounded-lg border border-amber-300 p-3 text-sm">
                        Los filtros actuales no coinciden con el
                        periodo y grupo de la prenomina seleccionada.
                        Selecciona nuevamente la nomina para consultar
                        su seguimiento.
                    </div>
                @else
                    <div class="mb-3 flex flex-wrap gap-3 text-sm">
                        <span>
                            <strong>Nomina:</strong>
                            {{ $reviewOverview['run_name'] }}
                        </span>
                        <span>
                            <strong>Lote:</strong>
                            {{ $reviewOverview['batch_id'] ?? 'No creado' }}
                        </span>
                        <span>
                            <strong>Estado:</strong>
                            {{ $reviewStatusLabels[$reviewOverview['batch_status']] ?? $reviewOverview['batch_status'] }}
                        </span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-4">
                        @foreach([
                            'Empleados del periodo' => $reviewOverview['total_employees'],
                            'Con responsable' => $reviewOverview['assigned_employees'],
                            'Sin responsable' => $reviewOverview['pending_employees'],
                            'Responsables distintos' => $reviewOverview['reviewer_groups'],
                        ] as $label => $value)
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                <div class="text-xs text-gray-500">
                                    {{ $label }}
                                </div>
                                <div class="text-xl font-bold">
                                    {{ $value }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 text-sm">
                        <strong>Integridad de fotografias:</strong>
                        Sin cambios:
                        {{ $reviewOverview['snapshot_integrity']['unchanged'] }}
                        · Modificadas:
                        {{ $reviewOverview['snapshot_integrity']['changed'] }}
                        · Invalidas:
                        {{ $reviewOverview['snapshot_integrity']['invalid_snapshot'] }}
                        · Faltantes:
                        {{ $reviewOverview['snapshot_integrity']['missing_snapshot'] }}
                    </div>

                    @if($reviewOverview['pending_employees'] > 0)
                        <div class="mt-3 rounded-lg border border-amber-300 p-3">
                            <h4 class="mb-2 text-sm font-semibold">
                                Empleados pendientes de responsable
                            </h4>
                            <div class="space-y-2 text-sm">
                                @foreach($reviewOverview['pending'] as $pending)
                                    <div class="border-b border-gray-100 pb-2 last:border-0 dark:border-gray-700">
                                        <strong>{{ $pending['employee_name'] }}</strong>
                                        <span class="text-gray-500">
                                            (#{{ $pending['employee_id'] }})
                                            · Departamento:
                                            {{ $pending['department_id'] ?? 'Sin asignar' }}
                                            · {{ $pending['reason'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($reviewOverview['review_statuses']) > 0)
                        <div class="mt-3 text-xs text-gray-500">
                            Revisiones:
                            @foreach($reviewOverview['review_statuses'] as $status => $count)
                                {{ $reviewStatusLabels[$status] ?? $status }}: {{ $count }}
                                @unless($loop->last) · @endunless
                            @endforeach
                        </div>
                    @endif

                    @if($reviewOverview['batch_status'] === 'sent'
                        && $reviewOverview['can_manage_reviews'])
                        <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="mb-3">
                                <h4 class="font-semibold">
                                    Seguimiento por responsable
                                </h4>
                                <p class="text-xs text-gray-500">
                                    Consulta de revisiones enviadas.
                                    No modifica la prenómina.
                                </p>
                            </div>

                            <div class="mb-4 grid gap-3 md:grid-cols-3">
                                @foreach([
                                    'Pendientes' => $reviewOverview['review_statuses']['pending'] ?? 0,
                                    'Con visto bueno' => $reviewOverview['review_statuses']['approved'] ?? 0,
                                    'Devueltas' => $reviewOverview['review_statuses']['returned'] ?? 0,
                                ] as $label => $count)
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <div class="text-xs text-gray-500">
                                            {{ $label }}
                                        </div>
                                        <div class="text-xl font-bold">
                                            {{ $count }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="space-y-3">
                                @forelse($reviewOverview['review_tracking'] as $tracking)
                                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <strong>
                                                {{ $tracking['reviewer_name'] }}
                                            </strong>
                                            <span class="rounded-lg border px-2 py-1 text-xs font-semibold">
                                                {{ $reviewStatusLabels[$tracking['status']] ?? $tracking['status'] }}
                                            </span>
                                        </div>

                                        <div class="mt-2 grid gap-2 text-sm md:grid-cols-2">
                                            <div>
                                                <span class="text-gray-500">
                                                    Enviado:
                                                </span>
                                                {{ $tracking['sent_at'] ?? 'Sin registro' }}
                                            </div>
                                            <div>
                                                <span class="text-gray-500">
                                                    Respondido:
                                                </span>
                                                {{ $tracking['reviewed_at'] ?? 'Pendiente' }}
                                            </div>
                                        </div>

                                        <div class="mt-3 text-sm">
                                            <strong>
                                                Empleados asignados
                                                ({{ count($tracking['employees']) }})
                                            </strong>
                                            <div class="mt-1 space-y-1">
                                                @foreach($tracking['employees'] as $employee)
                                                    <div>
                                                        {{ $employee['name'] }}
                                                        <span class="text-xs text-gray-500">
                                                            (#{{ $employee['id'] }})
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        @if(filled($tracking['comment']))
                                            <div class="mt-3 rounded-lg border border-amber-300 p-3 text-sm">
                                                <strong>
                                                    Observaciones del responsable
                                                </strong>
                                                <p class="mt-1 whitespace-pre-wrap">
                                                    {{ $tracking['comment'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">
                                        No se encontraron revisiones del lote.
                                    </p>
                                @endforelse
                            </div>

                            <p class="mt-3 text-xs text-gray-500">
                                Actualiza la página para consultar
                                las respuestas más recientes.
                            </p>
                        </div>
                    @endif

                    @if($reviewOverview['batch_status'] === 'sent'
                        && $reviewOverview['can_manage_reviews']
                        && ($reviewOverview['review_statuses']['returned'] ?? 0) > 0
                        && ($reviewOverview['review_statuses']['pending'] ?? 0) === 0
                        && ($reviewOverview['review_statuses']['approved'] ?? 0) === 0)
                        <div class="mt-3 flex justify-end">
                            <button type="button"
                                wire:click="openReviewResendConfirmation"
                                wire:loading.attr="disabled"
                                wire:target="openReviewResendConfirmation"
                                style="background-color: #15803d; color: #ffffff;"
                                class="rounded-lg px-4 py-2 text-sm font-semibold">
                                Preparar reenvío
                            </button>
                        </div>
                    @endif

                    @if($reviewOverview['batch_status'] === 'draft')
                    <div class="mt-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <h4 class="mb-2 text-sm font-semibold">
                            Preparacion operativa de revisiones
                        </h4>

                        <div class="grid gap-3 text-sm md:grid-cols-3">
                            <div>
                                <span class="text-xs text-gray-500">
                                    Estado del borrador
                                </span>
                                <div class="font-semibold">
                                    {{ $reviewStatusLabels[$reviewOverview['draft_inspection_status']] ?? $reviewOverview['draft_inspection_status'] }}
                                </div>
                            </div>

                            <div>
                                <span class="text-xs text-gray-500">
                                    Preparacion de datos
                                </span>
                                <div class="font-semibold">
                                    {{ $reviewOverview['draft_ready'] ? 'Lista' : 'Requiere revision' }}
                                </div>
                            </div>

                            <div>
                                <span class="text-xs text-gray-500">
                                    Permiso de gestion RRHH
                                </span>
                                <div class="font-semibold">
                                    {{ $reviewOverview['can_manage_reviews'] ? 'Autorizado' : 'Sin permiso especifico' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if($reviewOverview['can_manage_reviews'])
                                <button type="button"
                                    wire:click="checkAttendanceReviewSendReadiness"
                                    wire:loading.attr="disabled"
                                    wire:target="checkAttendanceReviewSendReadiness"
                                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold dark:border-gray-600">
                                    Comprobar preparacion
                                </button>
                            @else
                                <span class="text-xs text-amber-700 dark:text-amber-400">
                                    Para gestionar revisiones se requiere
                                    el permiso especifico de RRHH.
                                </span>
                            @endif

                            @if($reviewOverview['can_manage_reviews'])
                                <button type="button"
                                    wire:click="openReviewSendConfirmation"
                                    wire:loading.attr="disabled"
                                    wire:target="openReviewSendConfirmation"
                                    style="background-color: #15803d; color: #ffffff;"
                                    class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm">
                                    Revisar envío a responsables
                                </button>
                            @else
                                <button type="button" disabled
                                    class="cursor-not-allowed rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-500 opacity-70 dark:bg-gray-700">
                                    Enviar a responsables
                                </button>
                            @endif
                        </div>

                        <p class="mt-3 text-xs text-gray-500">
                            Comprueba la preparación antes de enviar.
                            El envío solamente se ejecuta al confirmar
                            desde la ventana de revisión.
                        </p>
                    </div>
                    @endif
                @endif
            </section>
        @endif

        @if($showReviewResendConfirmation)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900"
                    role="dialog" aria-modal="true"
                    aria-label="Confirmación de reenvío">
                    <h3 class="text-lg font-bold">
                        Preparar reenvío de revisiones
                    </h3>

                    <div class="mt-3 text-sm">
                        <strong>Pre-nómina:</strong>
                        {{ $reviewResendPreview['run_name'] ?? '-' }}
                    </div>
                    <div class="mt-1 text-sm">
                        <strong>Versión actual:</strong>
                        {{ $reviewResendPreview['version'] ?? '-' }}
                    </div>

                    <div class="mt-4 max-h-48 space-y-2 overflow-y-auto">
                        @foreach(($reviewResendPreview['returned'] ?? []) as $returned)
                            <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                                <strong>{{ $returned['reviewer_name'] }}</strong>
                                <div class="text-xs text-gray-500">
                                    Empleados: {{ $returned['employee_count'] }}
                                </div>
                                <div class="mt-2 whitespace-pre-wrap">
                                    {{ $returned['comment'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <label for="resend-review-reason"
                        class="mt-4 block text-sm font-semibold">
                        Justificación del reenvío (obligatoria)
                    </label>
                    <textarea id="resend-review-reason"
                        wire:model="reviewResendReason"
                        maxlength="2000"
                        rows="3"
                        class="mt-2 w-full rounded-lg border border-gray-300 p-3 text-sm dark:border-gray-600 dark:bg-gray-800"
                        placeholder="Describe qué se revisó o corrigió antes de reenviar..."></textarea>

                    <p class="mt-2 text-xs text-gray-500">
                        Se conservará la devolución anterior y se abrirá
                        una nueva versión pendiente para los responsables.
                        No se modifican automáticamente los datos de asistencia.
                    </p>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button"
                            wire:click="closeReviewResendConfirmation"
                            class="rounded-lg border px-4 py-2 text-sm">
                            Cancelar
                        </button>
                        <button type="button"
                            wire:click="confirmReviewResend"
                            wire:loading.attr="disabled"
                            wire:target="confirmReviewResend"
                            style="background-color: #15803d; color: #ffffff;"
                            class="rounded-lg px-4 py-2 text-sm font-semibold">
                            Confirmar reenvío
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if($showReviewSendConfirmation)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900"
                    role="dialog" aria-modal="true"
                    aria-label="Confirmacion de envio de revisiones">
                    <h3 class="text-lg font-bold">
                        Revisar envio a responsables
                    </h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Comprueba los datos antes de confirmar.
                        El envío quedará registrado y notificará
                        el estado pendiente de revisión en Bexia.
                    </p>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="col-span-2">
                            <dt class="text-gray-500">Prenomina</dt>
                            <dd class="font-semibold">
                                {{ $reviewSendPreview['run_name'] ?? '-' }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-gray-500">Periodo</dt>
                            <dd>
                                {{ $reviewSendPreview['period_start'] ?? '-' }}
                                a
                                {{ $reviewSendPreview['period_end'] ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Empleados</dt>
                            <dd class="font-semibold">
                                {{ $reviewSendPreview['employees'] ?? 0 }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Responsables</dt>
                            <dd class="font-semibold">
                                {{ $reviewSendPreview['reviewers'] ?? 0 }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Version</dt>
                            <dd class="font-semibold">
                                {{ $reviewSendPreview['batch_version'] ?? '-' }}
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button"
                            wire:click="closeReviewSendConfirmation"
                            class="rounded-lg border px-4 py-2 text-sm">
                            Cerrar
                        </button>
                        <button type="button"
                            wire:click="confirmReviewSend"
                            wire:loading.attr="disabled"
                            wire:target="confirmReviewSend"
                            style="background-color: #15803d; color: #ffffff;"
                            class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm">
                            Confirmar envío
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if($data['error'])
            <div class="rounded-lg border border-amber-300 p-4 text-sm">
                {{ $data['error'] }}
            </div>
        @else
            <div class="grid gap-3 md:grid-cols-4">
                @foreach([
                    'Empleados' => $data['totals']['employees'],
                    'Días con asistencia' => $data['totals']['attendance'],
                    'Marcajes incompletos' => $data['totals']['incomplete'],
                    'Posibles faltas' => $data['totals']['possible_absences'],
                    'Días con retardo' => $data['totals']['late_days'],
                    'Salidas anticipadas' => $data['totals']['early_days'],
                    'Horas extra registradas' => number_format(
                        $data['totals']['overtime_minutes'] / 60, 2
                    ),
                ] as $label => $value)
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                        <div class="text-xs text-gray-500">{{ $label }}</div>
                        <div class="text-2xl font-bold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button"
                    wire:click="exportExcel"
                    wire:loading.attr="disabled"
                    style="background-color:#15803d;color:#ffffff;padding:10px 18px;border-radius:8px;font-weight:600;cursor:pointer;">
                    Exportar Excel
                </button>
                <button type="button"
                    wire:click="exportPdf"
                    wire:loading.attr="disabled"
                    style="background-color:#1d4ed8;color:#ffffff;padding:10px 18px;border-radius:8px;font-weight:600;cursor:pointer;">
                    Exportar PDF
                </button>
            </div>

            <div class="text-xs text-gray-500">
                Vista informativa. No genera faltas, incidencias ni descuentos.
                F? = posible falta pendiente de validación.
                Las horas extra registradas no equivalen a horas autorizadas.
                Selecciona una celda para consultar los marcajes e incidencias.
            </div>

            @if($this->selectedEmployeeId && $this->selectedDate)
                @php
                    $detail = $this->dayDetail();
                @endphp

                @if($detail)
                    <div class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="mb-3 flex items-center justify-between">
                            <div>
                                <div class="text-base font-bold">
                                    {{ $detail['employee'] }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    Detalle del {{ $detail['date'] }}
                                </div>
                            </div>
                            <button type="button"
                                wire:click="closeDay"
                                class="rounded-lg border px-3 py-2 text-sm">
                                Cerrar
                            </button>
                        </div>

                        @php
                            $a = $detail['attendance'];
                        @endphp

                        @if($a)
                            <div class="grid gap-3 md:grid-cols-4">
                                @foreach([
                                    'Entrada' => $a->clock_in_at,
                                    'Salida final' => $a->clock_out_at,
                                    'Retardo (min)' => $a->late_minutes,
                                    'Salida anticipada (min)' => $a->early_leave_minutes,
                                    'Tiempo trabajado (min)' => $a->worked_minutes,
                                    'Horas extra registradas (min)' => $a->overtime_minutes,
                                ] as $label => $value)
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <div class="text-xs text-gray-500">
                                            {{ $label }}
                                        </div>
                                        <div class="font-semibold">
                                            {{ $value ?? 'Sin registro' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-sm text-gray-500">
                                Sin marcajes de asistencia para este día.
                            </div>
                        @endif

                        <div class="mt-4 text-sm font-semibold">
                            Registros de comida
                        </div>

                        @if($detail['breaks']->isEmpty())
                            <div class="text-sm text-gray-500">
                                Sin registros de comida.
                            </div>
                        @else
                            <div class="space-y-2">
                                @foreach($detail['breaks'] as $break)
                                    <div class="rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800">
                                        @foreach((array) $break as $field => $value)
                                            @if(
                                                str_contains($field, 'at')
                                                || str_contains($field, 'minute')
                                                || str_contains($field, 'duration')
                                            )
                                                <span class="mr-4 inline-block">
                                                    {{ $field }}:
                                                    {{ $value ?? '-' }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 text-sm font-semibold">
                            Incidencias registradas
                        </div>

                        @forelse($detail['incidents'] as $incident)
                            <div class="mt-2 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
                                <strong>{{ $incident->type_name }}</strong>
                                ({{ $incident->code }})
                                · Estado: {{ $incident->status }}
                                @if($incident->start_time || $incident->end_time)
                                    · Horario:
                                    {{ $incident->start_time ?? '-' }}
                                    a {{ $incident->end_time ?? '-' }}
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">
                                No hay incidencias registradas para esta fecha.
                            </div>
                        @endforelse
                    </div>
                @endif
            @endif

            <div class="overflow-x-auto rounded-xl bg-white shadow-sm dark:bg-gray-900">
                <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="sticky left-0 bg-gray-100 p-3 text-left dark:bg-gray-800">
                                Empleado
                            </th>
                            @foreach($data['days'] as $day)
                                <th class="p-2 text-center">
                                    {{ $day['weekday'] }}
                                    <div>
                                        {{ \Carbon\CarbonImmutable::parse($day['date'])->format('d/m/Y') }}
                                    </div>
                                </th>
                            @endforeach
                            <th class="p-2">Asist.</th>
                            <th class="p-2">Faltas?</th>
                            <th class="p-2">Incompl.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($data['rows'] as $row)
                            <tr>
                                <td class="sticky left-0 bg-white p-3 dark:bg-gray-900">
                                    <div class="font-semibold">{{ $row['name'] }}</div>
                                    <div class="text-gray-500">{{ $row['schedule'] }}</div>
                                </td>
                                @foreach($row['days'] as $day)
                                    @php
                                        $code = $day['code'];
                                        $color = match($code) {
                                            'F?' => 'bg-red-100 text-red-800',
                                            'I' => 'bg-amber-100 text-amber-800',
                                            'R' => 'bg-yellow-100 text-yellow-800',
                                            'A' => 'bg-green-100 text-green-800',
                                            'J' => 'bg-blue-100 text-blue-800',
                                            'P', 'PSG', 'V', 'INC' => 'bg-blue-100 text-blue-800',
                                            'PP', 'RH' => 'bg-amber-100 text-amber-800',
                                            'FL' => 'bg-gray-100 text-gray-600',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <td class="p-1 text-center">
                                        <button type="button"
                                            wire:click="showDay({{ (int) $row['id'] }}, '{{ $day['date'] }}')"
                                            class="inline-block min-w-8 rounded px-1 py-2 font-bold {{ $color }}"
                                            title="Ver marcajes e incidencias del {{ $day['date'] }}">
                                            {{ $code }}
                                            @if($day['has_incident'])
                                                <span title="Incidencia registrada">•</span>
                                            @endif
                                        </button>
                                    </td>
                                @endforeach
                                <td class="p-2 text-center">
                                    {{ $row['counts']['A'] + $row['counts']['R'] }}
                                </td>
                                <td class="p-2 text-center">
                                    {{ $row['counts']['F?'] }}
                                </td>
                                <td class="p-2 text-center">
                                    {{ $row['counts']['I'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($data['days']) + 4 }}"
                                    class="p-6 text-center">
                                    No hay empleados para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="text-xs text-gray-500">
                A = asistencia · R = retardo · I = incompleto ·
                F? = posible falta · D = descanso · SH = sin horario ·
                P = permiso con goce · FL = horario flexible/abierto sin marcaje · PSG = permiso sin goce ·
                V = vacaciones · INC = incapacidad · PP = pendiente de aprobación ·
                RH = revisar incidencia parcial ·
                NA = fuera del periodo laboral · HOY = día actual o futuro.
            </div>
        @endif
    </div>
</x-filament-panels::page>
