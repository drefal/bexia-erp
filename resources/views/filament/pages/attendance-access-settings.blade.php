<x-filament-panels::page>

    <div class="space-y-5">

        <x-filament::section>

            <x-slot name="heading">
                Alcance de la configuración
            </x-slot>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                <div>
                    <label class="text-sm font-semibold">
                        Empresa
                    </label>

                    <div class="mt-2 rounded-lg border border-gray-300 p-3 dark:border-gray-700">
                        {{ $companyName }}
                    </div>
                </div>

                <div>
                    <label for="access-policy-scope" class="text-sm font-semibold">
                        Aplicar a
                    </label>

                    <select
                        id="access-policy-scope"
                        wire:model.live="scope"
                        wire:loading.attr="disabled"
                        class="mt-2 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                    >
                        <option value="company">
                            Configuración general de empresa
                        </option>

                        @foreach($branches as $id => $name)
                            <option value="{{ $id }}">
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            <div style="margin-top:16px;padding:12px;border:1px solid #cbd5e1;border-radius:10px;">

                <strong>{{ $sourceLabel }}</strong>

                @if($policySource === 'inherited')
                    <p class="mt-1 text-sm text-gray-500">
                        Esta sucursal utiliza la política general.
                        Guardar aquí creará una configuración propia.
                    </p>
                @elseif($policySource === 'branch')
                    <p class="mt-1 text-sm text-gray-500">
                        La política propia prevalece sobre la general.
                    </p>
                @elseif($policySource === 'default')
                    <p class="mt-1 text-sm text-gray-500">
                        No existe una política guardada para este alcance.
                    </p>
                @else
                    <p class="mt-1 text-sm text-gray-500">
                        Se aplica a las sucursales sin política propia.
                    </p>
                @endif

            </div>

        </x-filament::section>

        <x-filament::section>

            <x-slot name="heading">
                Reglas de ingreso
            </x-slot>

            <div class="space-y-4">

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model.live="policy.enabled"
                        class="mt-1 rounded"
                    />

                    <span>
                        <strong>Activar control de acceso</strong>
                        <span class="block text-sm text-gray-500">
                            Evaluar los registros de ingreso según las políticas.
                        </span>
                    </span>
                </label>

                <hr class="border-gray-200 dark:border-gray-700"/>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="policy.hold_on_late"
                        class="mt-1 rounded"
                    />

                    <span>
                        <strong>Retener por retardos y fuera de horario</strong>
                        <span class="block text-sm text-gray-500">
                            Requerir autorización al exceder la tolerancia o registrar el ingreso después del término de la jornada.
                        </span>
                    </span>
                </label>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                    <div>
                        <label for="access-tolerance" class="text-sm font-semibold">
                            Tolerancia personalizada (minutos)
                        </label>

                        <input
                            id="access-tolerance"
                            type="number"
                            min="0"
                            max="1440"
                            step="1"
                            wire:model="policy.access_tolerance_minutes"
                            placeholder="Tolerancia del horario"
                            class="mt-2 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        />

                        <p class="mt-1 text-xs text-gray-500">
                            Vacío: usar la tolerancia del horario del empleado.
                            Cero: sin tolerancia.
                        </p>

                        @error('policy.access_tolerance_minutes')
                            <p class="text-sm text-danger-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="access-early" class="text-sm font-semibold">
                            Entrada anticipada (minutos)
                        </label>

                        <input
                            id="access-early"
                            type="number"
                            min="0"
                            max="1440"
                            step="1"
                            wire:model="policy.early_arrival_minutes"
                            placeholder="Sin límite"
                            class="mt-2 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        />

                        <p class="mt-1 text-xs text-gray-500">
                            Vacío: sin límite de anticipación.
                        </p>

                        @error('policy.early_arrival_minutes')
                            <p class="text-sm text-danger-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                </div>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="policy.hold_on_rest_day"
                        class="mt-1 rounded"
                    />
                    <span>
                        <strong>Retener en días de descanso</strong>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="policy.hold_on_approved_absence"
                        class="mt-1 rounded"
                        disabled
                    />
                    <span>
                        <strong>Retener durante ausencias aprobadas</strong>
                        <span class="block text-sm text-gray-500">
                            Pendiente de integración con incidencias de RRHH.
                        </span>
                    </span>
                </label>

                <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:9px;padding:12px;color:#92400e;font-size:13px;">
                    La activación de nuevas reglas de retención
                    está protegida temporalmente hasta validar
                    la cobertura de responsables.
                </div>

            </div>

        </x-filament::section>

        <x-filament::section>

            <x-slot name="heading">
                Responsables de autorización
            </x-slot>

            <div class="space-y-4">

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="policy.allow_manager_approval"
                        class="mt-1 rounded"
                    />
                    <span>
                        <strong>Jefe directo</strong>
                        <span class="block text-sm text-gray-500">
                            Primera prioridad según el organigrama.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="policy.allow_department_approval"
                        class="mt-1 rounded"
                    />
                    <span>
                        <strong>Responsable de departamento</strong>
                        <span class="block text-sm text-gray-500">
                            Requiere vinculación y permiso específico.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="policy.allow_hr_approval"
                        class="mt-1 rounded"
                    />
                    <span>
                        <strong>Personal autorizado de RRHH</strong>
                        <span class="block text-sm text-gray-500">
                            Requiere permiso de autorización de RRHH.
                        </span>
                    </span>
                </label>

                <p class="text-sm text-gray-500">
                    Habilitar una opción no asigna permisos
                    automáticamente a los usuarios.
                </p>

            </div>

        </x-filament::section>

        <x-filament::section>

            <x-slot name="heading">
                Cobertura de responsables
            </x-slot>

            <div class="space-y-4">

                <p class="text-sm text-gray-500">
                    Simula la cobertura con los valores actuales
                    del formulario sin guardar la política.
                    Incluye a todos los empleados activos de la
                    empresa que podrían utilizar esta terminal.
                </p>

                @if($scope === 'company')
                    <p class="text-sm text-gray-500">
                        Selecciona una sucursal para calcular
                        su cobertura de ingreso.
                    </p>
                @else
                    <x-filament::button
                        color="gray"
                        wire:click="calculateCoverage"
                        wire:loading.attr="disabled"
                    >
                        Calcular cobertura
                    </x-filament::button>
                @endif

                <p
                    wire:loading
                    wire:target="calculateCoverage"
                    class="text-sm text-gray-500"
                >
                    Calculando responsables...
                </p>

                @if($coverageResult !== null && $scope === $loadedScope)

                    @php
                        $summary = $coverageResult['summary'];

                        $percent = $summary['total'] > 0
                            ? round(
                                $summary['covered']
                                * 100
                                / $summary['total'],
                                2
                            )
                            : 0;

                        $reasonLabels = [
                            'direct_manager' =>
                                'Jefe directo',
                            'department_manager' =>
                                'Responsable de departamento',
                            'hr_authority' =>
                                'Personal autorizado RRHH',
                            'no_direct_manager' =>
                                'Sin jefe directo',
                            'invalid_direct_manager' =>
                                'Jefe no válido',
                            'inactive_direct_manager' =>
                                'Jefe inactivo',
                            'manager_without_user' =>
                                'Jefe sin usuario vinculado',
                            'no_eligible_authorizer' =>
                                'Sin autorizador elegible',
                            'policy_disabled' =>
                                'Política desactivada',
                        ];
                    @endphp

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">

                        @foreach([
                            'total' => 'Empleados activos',
                            'covered' => 'Con autorizador',
                            'uncovered' => 'Sin autorizador',
                        ] as $key => $label)

                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">

                                <div class="text-sm text-gray-500">
                                    {{ $label }}
                                </div>

                                <div style="font-size:26px;font-weight:700;color:{{ $key === 'covered' ? '#15803d' : ($key === 'uncovered' ? '#b91c1c' : 'inherit') }};">

                                    {{ $summary[$key] }}

                                </div>

                            </div>

                        @endforeach

                    </div>

                    @if($summary['not_evaluated'] > 0)

                        <p class="text-sm text-gray-500">
                            Política desactivada:
                            {{ $summary['not_evaluated'] }}
                            empleados no evaluados.
                        </p>

                    @else

                        <div style="height:12px;background:#e5e7eb;border-radius:8px;overflow:hidden;">

                            <div style="height:100%;background:#16a34a;width:{{ $percent }}%;"></div>

                        </div>

                        <p class="text-sm font-medium">
                            Cobertura: {{ $percent }} %
                        </p>

                    @endif

                    <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-2">

                        <p>
                            Jefe directo:
                            <strong>{{ $summary['direct_manager'] }}</strong>
                        </p>

                        <p>
                            Responsable de departamento:
                            <strong>{{ $summary['department_manager'] }}</strong>
                        </p>

                        <p>
                            Personal RRHH:
                            <strong>{{ $summary['hr_authority'] }}</strong>
                        </p>

                        <p>
                            Sin sucursal:
                            <strong>{{ $summary['without_branch'] }}</strong>
                        </p>

                        <p>
                            QR habilitado:
                            <strong>{{ $summary['qr_enabled'] }}</strong>
                        </p>

                        <p>
                            QR sin autorizador:
                            <strong>{{ $summary['qr_uncovered'] }}</strong>
                        </p>

                    </div>

                    @if($summary['uncovered'] > 0)

                        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:9px;padding:12px;color:#92400e;font-size:13px;">
                            Existen empleados sin cobertura.
                            La activación de retenciones continúa
                            protegida.
                        </div>

                    @endif

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">

                        <select
                            wire:model.live="coverageFilter"
                            class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        >
                            <option value="all">
                                Todos los empleados
                            </option>
                            <option value="uncovered">
                                Sin autorizador
                            </option>
                            <option value="covered">
                                Con autorizador
                            </option>
                            <option value="without_user">
                                Jefe sin usuario vinculado
                            </option>
                            <option value="without_branch">
                                Sin sucursal asignada
                            </option>
                            <option value="qr_uncovered">
                                QR habilitado sin autorizador
                            </option>
                        </select>

                        <input
                            type="search"
                            wire:model.live.debounce.400ms="coverageSearch"
                            placeholder="Buscar empleado o jefe..."
                            maxlength="100"
                            class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        />

                    </div>

                    <div style="max-height:420px;overflow:auto;">

                        <table class="w-full text-sm">

                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="p-2 text-left">Empleado</th>
                                    <th class="p-2 text-left">Jefe directo</th>
                                    <th class="p-2 text-left">Cobertura</th>
                                    <th class="p-2 text-left">QR</th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse($coverageRows as $row)

                                    <tr
                                        wire:key="coverage-employee-{{ $row['employee_id'] }}"
                                        class="border-b border-gray-200 dark:border-gray-700"
                                    >

                                        <td class="p-2">

                                            {{ $row['employee_name'] }}

                                            @if($row['scope'] === 'without_branch')
                                                <div class="text-xs text-gray-500">
                                                    Sin sucursal
                                                </div>
                                            @elseif($row['scope'] === 'other_branch')
                                                <div class="text-xs text-gray-500">
                                                    Otra sucursal
                                                </div>
                                            @endif

                                        </td>

                                        <td class="p-2">
                                            {{ $row['manager_name'] ?? 'Sin asignar' }}
                                        </td>

                                        <td class="p-2">

                                            <span style="color:{{ $row['status'] === 'covered' ? '#15803d' : ($row['status'] === 'uncovered' ? '#b91c1c' : '#64748b') }};">

                                                {{ $reasonLabels[$row['reason_code']] ?? 'Requiere revisión' }}

                                            </span>

                                        </td>

                                        <td class="p-2">
                                            {{ $row['qr_enabled'] ? 'Sí' : 'No' }}
                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td colspan="4" class="p-4 text-center text-gray-500">
                                            No hay empleados para este filtro.
                                        </td>
                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                    <p class="text-xs text-gray-500">

                        Resultados: {{ count($coverageRows) }}.
                        Calculado: {{ $coverageResult['calculated_at'] }}.
                        Simulación de configuración, no guardada.
                        Recalcula después de modificar usuarios o permisos.

                    </p>

                @endif

            </div>

        </x-filament::section>

        @if($errors->any())
            <div style="color:#b91c1c;font-size:13px;">

                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach

            </div>
        @endif

        <div class="flex flex-wrap justify-end gap-3">

            <x-filament::button
                color="gray"
                wire:click="reloadPolicy"
                wire:loading.attr="disabled"
            >
                Recargar configuración
            </x-filament::button>

            <x-filament::button
                wire:click="prepareSave"
                wire:loading.attr="disabled"
            >
                Guardar configuración
            </x-filament::button>

        </div>

    </div>

    <x-filament::modal
        id="hr-access-policy-confirm"
        width="lg"
        :close-by-clicking-away="false"
        :close-by-escaping="false"
        :close-button="false"
    >

        <x-slot name="heading">
            Confirmar cambios de configuración
        </x-slot>

        <x-slot name="description">
            {{ $companyName }} · {{ $scopeLabel }}
        </x-slot>

        @if($confirmedValues !== null)

            @php
                $labels = [
                    'enabled' => 'Control de acceso',
                    'hold_on_late' => 'Retardos y fuera de horario',
                    'hold_on_rest_day' => 'Retener en descanso',
                    'hold_on_approved_absence' => 'Retener por ausencia',
                    'allow_manager_approval' => 'Jefe directo',
                    'allow_department_approval' => 'Responsable de departamento',
                    'allow_hr_approval' => 'Autorizadores RRHH',
                    'access_tolerance_minutes' => 'Tolerancia',
                    'early_arrival_minutes' => 'Entrada anticipada',
                ];

                $format = static function ($field, $value) {
                    if (is_bool($value)) {
                        return $value ? 'Sí' : 'No';
                    }

                    if ($value === null) {
                        return $field === 'access_tolerance_minutes'
                            ? 'Según horario'
                            : 'Sin límite';
                    }

                    return $value . ' min';
                };
            @endphp

            <div class="space-y-3">

                @if($policyId === null)
                    <p class="text-sm text-gray-500">
                        Se creará una política para el alcance seleccionado.
                    </p>
                @endif

                @foreach($confirmedValues as $field => $value)

                    @if($policyId === null || $value !== ($originalValues[$field] ?? null))

                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-2 dark:border-gray-700">

                            <span class="text-sm font-medium">
                                {{ $labels[$field] ?? $field }}
                            </span>

                            <span class="text-sm">
                                @if($policyId !== null)
                                    <span class="text-gray-500">
                                        {{ $format($field, $originalValues[$field] ?? null) }}
                                    </span>
                                    →
                                @endif

                                <strong>{{ $format($field, $value) }}</strong>
                            </span>

                        </div>

                    @endif

                @endforeach

                <div>
                    <label for="access-change-reason" class="text-sm font-semibold">
                        Motivo de la modificación *
                    </label>

                    <textarea
                        id="access-change-reason"
                        wire:model="changeReason"
                        rows="3"
                        maxlength="1000"
                        placeholder="Indica el motivo del cambio..."
                        class="mt-2 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                    ></textarea>

                    @error('changeReason')
                        <p class="text-sm text-danger-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <p class="text-xs text-gray-500">
                    El usuario, motivo, fecha y valores modificados
                    quedarán registrados en la auditoría.
                </p>

            </div>

        @endif

        <x-slot name="footerActions">

            <x-filament::button
                color="gray"
                wire:click="cancelSave"
                wire:loading.attr="disabled"
            >
                Cancelar
            </x-filament::button>

            @if($confirmedValues !== null)
                <x-filament::button
                    color="success"
                    wire:click="savePolicy"
                    wire:loading.attr="disabled"
                >
                    Confirmar y guardar
                </x-filament::button>
            @endif

        </x-slot>

    </x-filament::modal>

</x-filament-panels::page>
