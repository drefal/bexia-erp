<x-filament-panels::page>

    <div class="space-y-4" wire:poll.15s>

        <x-filament::section>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="font-size:17px;font-weight:700;">
                        Accesos pendientes de autorización
                    </h2>

                    <p style="color:#64748b;font-size:13px;">
                        {{ $companyName }} · Solicitudes de ingreso del día
                    </p>
                </div>

                <span style="padding:7px 12px;border-radius:999px;font-weight:700;color:{{ $pendingCount ? '#991b1b' : '#475569' }};background:{{ $pendingCount ? '#fee2e2' : '#f1f5f9' }};">
                    {{ $pendingCount }} pendientes
                </span>
            </div>

            <p style="font-size:12px;color:#64748b;margin-top:10px;">
                Autorizar un ingreso no modifica automáticamente las incidencias de nómina.
            </p>
        </x-filament::section>

        @forelse($pendingRows as $row)

            <div
                wire:key="hr-access-{{ $row['id'] }}-{{ $row['version'] }}"
                style="background:white;border:1px solid #e2e8f0;border-left:5px solid #dc2626;border-radius:12px;padding:18px;"
            >

                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">

                    <div>
                        <div style="font-size:17px;font-weight:700;">
                            {{ $row['employee_name'] }}
                        </div>

                        <div style="font-size:13px;color:#64748b;">
                            {{ $row['branch_name'] }}
                            · Folio #{{ $row['id'] }}
                        </div>
                    </div>

                    <span style="background:#fee2e2;color:#991b1b;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:800;">
                        RETENER
                    </span>

                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-top:16px;">

                    <div>
                        <div style="font-size:12px;color:#64748b;">
                            Entrada registrada
                        </div>
                        <strong>{{ $row['entry_at'] }}</strong>
                    </div>

                    <div>
                        <div style="font-size:12px;color:#64748b;">
                            Horario de entrada
                        </div>
                        <strong>{{ $row['schedule_start'] ?? 'No aplica' }}</strong>
                    </div>

                    <div>
                        <div style="font-size:12px;color:#64748b;">
                            Tolerancia
                        </div>
                        <strong>
                            {{ $row['tolerance'] !== null ? $row['tolerance'].' min' : 'No aplica' }}
                        </strong>
                    </div>

                    @if($row['reason_code'] === 'late_requires_authorization')
                        <div>
                            <div style="font-size:12px;color:#64748b;">
                                Retardo después de tolerancia
                            </div>
                            <strong style="color:#b91c1c;">
                                @php
                                    $lateMinutes = max(0, (int) floor($row['late_minutes'] ?? 0));
                                @endphp
                                @if($lateMinutes >= 60)
                                    {{ intdiv($lateMinutes, 60) }} h {{ $lateMinutes % 60 }} min
                                @else
                                    {{ $lateMinutes }} min
                                @endif
                            </strong>
                        </div>
                    @endif

                </div>

                <div style="margin-top:12px;font-size:13px;color:#475569;">
                    {{ match($row['reason_code']) {
                        'late_requires_authorization' => 'Retardo pendiente de autorización.',
                        'non_working_day' => 'Ingreso en día de descanso.',
                        'outside_working_hours' => 'Fuera del horario permitido.',
                        'early_arrival_outside_limit' => 'Entrada anticipada fuera del límite.',
                        default => 'Ingreso pendiente de revisión.',
                    } }}
                </div>

                <div style="display:flex;justify-content:flex-end;margin-top:14px;">

                    <x-filament::button
                        wire:click="selectDecision({{ $row['id'] }}, {{ $row['version'] }})"
                        wire:loading.attr="disabled"
                        wire:target="selectDecision,authorizeEntry,rejectEntry"
                    >
                        Atender solicitud
                    </x-filament::button>

                </div>

            </div>

        @empty

            <x-filament::section>

                <div style="text-align:center;padding:30px;color:#64748b;">

                    <x-heroicon-o-check-circle
                        style="height:44px;width:44px;margin:0 auto 10px;color:#16a34a;"
                    />

                    <div style="font-size:16px;font-weight:600;color:#334155;">
                        No tienes accesos pendientes de autorización
                    </div>

                    <p style="font-size:13px;margin-top:6px;">
                        Las solicitudes aparecerán aquí cuando correspondan a tu alcance de autorización.
                    </p>

                </div>

            </x-filament::section>

        @endforelse

    </div>


    <x-filament::modal
        id="hr-access-resolution-modal"
        width="lg"
        :close-by-clicking-away="false"
        :close-by-escaping="false"
        :close-button="false"
    >

        <x-slot name="heading">
            Resolver solicitud de ingreso
        </x-slot>

        <x-slot name="description">
            Confirma la resolución del acceso del empleado.
        </x-slot>

        @if($selectedRow)

            <div class="space-y-4">

                <div style="font-size:18px;font-weight:700;">
                    {{ $selectedRow['employee_name'] }}
                </div>

                <div style="color:#64748b;font-size:13px;">
                    {{ $selectedRow['branch_name'] }}
                    · Folio #{{ $selectedRow['id'] }}
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;padding:14px;border:1px solid #cbd5e1;border-radius:10px;">

                    <div>
                        <div style="color:#64748b;font-size:12px;">
                            Horario de entrada
                        </div>
                        <strong>
                            {{ $selectedRow['schedule_start'] ?? 'No aplica' }}
                        </strong>
                    </div>

                    <div>
                        <div style="color:#64748b;font-size:12px;">
                            Entrada registrada
                        </div>
                        <strong>
                            {{ $selectedRow['entry_at'] }}
                        </strong>
                    </div>

                    <div>
                        <div style="color:#64748b;font-size:12px;">
                            Tolerancia
                        </div>
                        <strong>
                            {{ $selectedRow['tolerance'] !== null ? $selectedRow['tolerance'].' min' : 'No aplica' }}
                        </strong>
                    </div>

                    @if($selectedRow['reason_code'] === 'late_requires_authorization')
                        @php
                            $modalLate = max(0, (int) floor($selectedRow['late_minutes'] ?? 0));
                        @endphp

                        <div>
                            <div style="color:#64748b;font-size:12px;">
                                Retardo después de tolerancia
                            </div>

                            <strong style="color:#b91c1c;">
                                @if($modalLate >= 60)
                                    {{ intdiv($modalLate, 60) }} h {{ $modalLate % 60 }} min
                                @else
                                    {{ $modalLate }} min
                                @endif
                            </strong>
                        </div>
                    @endif

                </div>

                <div>

                    <label
                        for="hr-access-resolution-reason"
                        style="font-size:13px;font-weight:600;"
                    >
                        Motivo de la resolución *
                    </label>

                    <textarea
                        id="hr-access-resolution-reason"
                        wire:model="resolutionReason"
                        rows="4"
                        maxlength="1000"
                        placeholder="Indicar el motivo de autorización o rechazo..."
                        class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        style="margin-top:7px;"
                    ></textarea>

                    @error('resolutionReason')
                        <p style="color:#dc2626;font-size:12px;">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                <p style="font-size:12px;color:#64748b;">
                    Autorizar el ingreso no modifica automáticamente las incidencias de nómina.
                </p>

            </div>

        @else

            <p style="color:#b45309;font-size:14px;">
                Esta solicitud ya no se encuentra disponible.
                Puede haber sido atendida por otro responsable.
            </p>

        @endif

        <x-slot name="footerActions">

            <x-filament::button
                color="gray"
                wire:click="cancelResolution"
                wire:loading.attr="disabled"
                wire:target="authorizeEntry,rejectEntry,cancelResolution"
            >
                Cancelar
            </x-filament::button>

            @if($selectedRow && $selectedRow['can_reject'])

                <x-filament::button
                    color="danger"
                    wire:click="rejectEntry"
                    wire:loading.attr="disabled"
                    wire:target="authorizeEntry,rejectEntry,cancelResolution"
                >
                    Rechazar ingreso
                </x-filament::button>

            @endif

            @if($selectedRow && $selectedRow['can_approve'])

                <x-filament::button
                    color="success"
                    wire:click="authorizeEntry"
                    wire:loading.attr="disabled"
                    wire:target="authorizeEntry,rejectEntry,cancelResolution"
                >
                    Autorizar ingreso
                </x-filament::button>

            @endif

        </x-slot>

    </x-filament::modal>

</x-filament-panels::page>
