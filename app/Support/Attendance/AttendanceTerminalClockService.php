<?php

namespace App\Support\Attendance;

use App\Models\AttendanceTerminal;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeAttendanceBreak;
use App\Support\EmployeeAttendanceIncidentSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceTerminalClockService
{
    public const DUPLICATE_WINDOW_SECONDS = 60;

    public function register(
        AttendanceTerminal $terminal,
        string $rawEmployeeQr,
        string $requestedAction,
        UploadedFile $photo,
        string $ipAddress,
        string $userAgent,
    ): array {
        if (! $terminal->active || $terminal->isBlocked()) {
            throw ValidationException::withMessages([
                'terminal' => 'Esta terminal esta bloqueada o desactivada.',
            ]);
        }

        if (! $terminal->branch_id) {
            throw ValidationException::withMessages([
                'terminal' => 'La terminal no tiene una sucursal fisica asignada.',
            ]);
        }

        $employeeToken = $this->normalizeEmployeeToken($rawEmployeeQr);

        if ($employeeToken === '') {
            throw ValidationException::withMessages([
                'employee_qr' => 'La credencial QR no contiene un token valido.',
            ]);
        }

        $employee = Employee::query()
            ->with('company')
            ->where('attendance_qr_token', $employeeToken)
            ->where('attendance_qr_enabled', true)
            ->where('active', true)
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_qr' => 'Credencial no reconocida o desactivada.',
            ]);
        }

        if ((int) $employee->company_id !== (int) $terminal->company_id) {
            throw ValidationException::withMessages([
                'employee_qr' => 'Esta credencial no pertenece a la empresa autorizada para esta terminal.',
            ]);
        }

        if ($employee->company && isset($employee->company->attendance_qr_enabled) && ! (bool) $employee->company->attendance_qr_enabled) {
            throw ValidationException::withMessages([
                'employee_qr' => 'El registro de asistencia por QR esta desactivado para esta empresa.',
            ]);
        }

        $storedPhotoPath = null;
        $direction = null;
        $attendance = null;
        $clockedAt = null;

        try {
            DB::transaction(function () use (
                $terminal,
                $employee,
                $requestedAction,
                $photo,
                $ipAddress,
                $userAgent,
                &$storedPhotoPath,
                &$direction,
                &$attendance,
                &$clockedAt,
            ): void {
                // Bloquea al empleado para serializar dos lecturas simultaneas del mismo QR.
                Employee::query()
                    ->whereKey($employee->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $today = now()->toDateString();
                $clockedAt = now();

                $attendance = EmployeeAttendance::query()
                    ->where('employee_id', $employee->getKey())
                    ->whereDate('attendance_date', $today)
                    ->lockForUpdate()
                    ->first();

                $mealBreaks = collect();
                $openMealBreak = null;
                $mealBreakCount = 0;

                if ($attendance?->getKey()) {
                    $mealBreaks = EmployeeAttendanceBreak::query()
                        ->where(
                            'employee_attendance_id',
                            $attendance->getKey()
                        )
                        ->where('break_type', 'meal')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $openMealBreak = $mealBreaks
                        ->first(
                            fn (EmployeeAttendanceBreak $break): bool =>
                                (bool) $break->started_at
                                && ! $break->ended_at
                        );

                    $mealBreakCount = $mealBreaks
                        ->filter(
                            fn (EmployeeAttendanceBreak $break): bool =>
                                (bool) $break->started_at
                        )
                        ->count();
                }

                if ($attendance && $attendance->clock_in_at && $attendance->clock_out_at) {
                    throw ValidationException::withMessages([
                        'employee_qr' => 'Ya tienes entrada y salida registradas para hoy.',
                    ]);
                }

                $lastEventAt = $attendance?->clock_in_at;

                foreach ($mealBreaks as $mealBreak) {
                    if (
                        $mealBreak->started_at
                        && (
                            ! $lastEventAt
                            || $mealBreak->started_at->greaterThan(
                                $lastEventAt
                            )
                        )
                    ) {
                        $lastEventAt = $mealBreak->started_at;
                    }

                    if (
                        $mealBreak->ended_at
                        && (
                            ! $lastEventAt
                            || $mealBreak->ended_at->greaterThan(
                                $lastEventAt
                            )
                        )
                    ) {
                        $lastEventAt = $mealBreak->ended_at;
                    }
                }

                if ($lastEventAt) {
                    $secondsSinceLastEvent = $lastEventAt->diffInSeconds($clockedAt);

                    if ($secondsSinceLastEvent < self::DUPLICATE_WINDOW_SECONDS) {
                        throw ValidationException::withMessages([
                            'employee_qr' => 'Registro duplicado. Espera un momento antes de volver a pasar tu tarjeta.',
                        ]);
                    }
                }

                $requestedAction = strtolower(trim($requestedAction));
                $allowedActions = $this->allowedActions(
                    $attendance,
                    $openMealBreak,
                    $mealBreakCount
                );

                if (! in_array($requestedAction, $allowedActions, true)) {
                    throw ValidationException::withMessages([
                        'action' => 'La accion seleccionada ya no esta disponible. Escanea nuevamente tu credencial.',
                    ]);
                }

                $direction = $requestedAction;

                $storedPhotoPath = $this->storePhoto(
                    photo: $photo,
                    terminal: $terminal,
                    employee: $employee,
                    direction: $direction,
                    clockedAt: $clockedAt,
                );

                if (! $attendance) {
                    $attendance = new EmployeeAttendance();
                }

                $deviceFingerprint = hash(
                    'sha256',
                    'attendance-terminal|' . (string) $terminal->uuid
                );

                $deviceInfo = [
                    'device_type' => 'attendance_terminal',
                    'terminal_id' => $terminal->getKey(),
                    'terminal_uuid' => (string) $terminal->uuid,
                    'terminal_code' => (string) $terminal->code,
                    'terminal_name' => (string) $terminal->name,
                    'physical_company_id' => $terminal->company_id,
                    'physical_branch_id' => $terminal->branch_id,
                ];

                if ($direction === 'clock_in' || $direction === 'clock_out') {
                    $prefix = $direction;

                    $evidence = [
                        $prefix . '_attendance_terminal_id' => $terminal->getKey(),
                        $prefix . '_photo_path' => $storedPhotoPath,
                        $prefix . '_method' => 'terminal_qr',
                        $prefix . '_ip_address' => substr($ipAddress, 0, 255),
                        $prefix . '_user_agent' => substr($userAgent, 0, 1000),
                        $prefix . '_device_fingerprint' => $deviceFingerprint,
                        $prefix . '_device_info' => $deviceInfo,
                        $prefix . '_device_guard_status' => 'authorized_terminal',
                        $prefix . '_device_guard_message' => null,
                        $prefix . '_location_status' => 'terminal_authorized',
                        'mobile_review_status' => 'accepted',
                    ];

                    /*
                     * Si termina jornada estando en descanso, ese descanso
                     * concluye en la misma hora de salida.
                     */
                    if (
                        $direction === 'clock_out'
                        && $openMealBreak
                        && ! $openMealBreak->ended_at
                    ) {
                        $openMealBreak->forceFill([
                            'ended_at' => $clockedAt,
                            'end_attendance_terminal_id' => $terminal->getKey(),
                            'end_method' => 'terminal_qr',
                            'end_photo_path' => $storedPhotoPath,
                            'end_ip_address' => substr($ipAddress, 0, 45),
                            'end_user_agent' => substr($userAgent, 0, 1000),
                            'end_device_fingerprint' => $deviceFingerprint,
                            'end_device_info' => $deviceInfo,
                            'end_device_guard_status' => 'authorized_terminal',
                        ])->save();

                        $attendance->notes = $this->appendNote(
                            $attendance->notes,
                            'Descanso abierto cerrado automáticamente '
                            . 'al terminar jornada.'
                        );
                    }

                    if ($direction === 'clock_in') {
                        $attendance->forceFill(array_merge([
                            'company_id' => $employee->company_id,
                            'employee_id' => $employee->getKey(),
                            'attendance_date' => $today,
                            'clock_in_at' => $clockedAt,
                            'source' => 'terminal',
                            'notes' => $this->appendNote(
                                $attendance->notes,
                                'Entrada registrada en terminal '
                                . $terminal->code
                                . ' / '
                                . $terminal->name
                                . '.'
                            ),
                            'created_by_user_id' => null,
                            'updated_by_user_id' => null,
                        ], $evidence));
                    } else {
                        $attendance->forceFill(array_merge([
                            'clock_out_at' => $clockedAt,
                            'source' => $attendance->source ?: 'terminal',
                            'notes' => $this->appendNote(
                                $attendance->notes,
                                'Salida registrada en terminal '
                                . $terminal->code
                                . ' / '
                                . $terminal->name
                                . '.'
                            ),
                            'updated_by_user_id' => null,
                        ], $evidence));
                    }

                    $attendance->save();
                } elseif ($direction === 'meal_out') {
                    $mealBreak = new EmployeeAttendanceBreak();

                    $mealBreak->forceFill([
                        'employee_attendance_id' => $attendance->getKey(),
                        'company_id' => $employee->company_id,
                        'break_type' => 'meal',
                        'started_at' => $clockedAt,
                        'start_attendance_terminal_id' => $terminal->getKey(),
                        'start_method' => 'terminal_qr',
                        'start_photo_path' => $storedPhotoPath,
                        'start_ip_address' => substr($ipAddress, 0, 45),
                        'start_user_agent' => substr($userAgent, 0, 1000),
                        'start_device_fingerprint' => $deviceFingerprint,
                        'start_device_info' => $deviceInfo,
                        'start_device_guard_status' => 'authorized_terminal',
                    ])->save();

                    $attendance->forceFill([
                        'notes' => $this->appendNote(
                            $attendance->notes,
                            'Salida a descanso registrada en terminal '
                            . $terminal->code
                            . ' / '
                            . $terminal->name
                            . '.'
                        ),
                        'updated_by_user_id' => null,
                    ])->save();
                } elseif ($direction === 'meal_in') {
                    if (! $openMealBreak) {
                        throw ValidationException::withMessages([
                            'employee_qr' => 'No existe una salida a descanso pendiente.',
                        ]);
                    }

                    $mealBreak = $openMealBreak;

                    $mealBreak->forceFill([
                        'ended_at' => $clockedAt,
                        'end_attendance_terminal_id' => $terminal->getKey(),
                        'end_method' => 'terminal_qr',
                        'end_photo_path' => $storedPhotoPath,
                        'end_ip_address' => substr($ipAddress, 0, 45),
                        'end_user_agent' => substr($userAgent, 0, 1000),
                        'end_device_fingerprint' => $deviceFingerprint,
                        'end_device_info' => $deviceInfo,
                        'end_device_guard_status' => 'authorized_terminal',
                    ])->save();

                    $attendance->forceFill([
                        'notes' => $this->appendNote(
                            $attendance->notes,
                            'Regreso de descanso registrado en terminal '
                            . $terminal->code
                            . ' / '
                            . $terminal->name
                            . '.'
                        ),
                        'updated_by_user_id' => null,
                    ])->save();
                }            }, 3);
        } catch (\Throwable $e) {
            if ($storedPhotoPath) {
                Storage::disk('local')->delete($storedPhotoPath);
            }

            throw $e;
        }

        if (
            in_array(
                $direction,
                ['clock_in', 'meal_in', 'clock_out'],
                true
            )
            && $attendance
        ) {
            try {
                EmployeeAttendanceIncidentSync::syncAll($attendance->fresh(), null, true);
            } catch (\Throwable $e) {
                report($e);

                try {
                    $attendance->forceFill([
                        'notes' => $this->appendNote(
                            $attendance->notes,
                            'No se pudo generar incidencia automatica desde terminal: ' . $e->getMessage()
                        ),
                    ])->save();
                } catch (\Throwable $inner) {
                    report($inner);
                }
            }
        }

        return [
            'attendance_id' => $attendance?->getKey(),
            'direction' => $direction,
            'direction_label' => match ($direction) {
                'clock_in' => 'ENTRADA',
                'meal_out' => 'SALIDA A DESCANSO',
                'meal_in' => 'REGRESO DE DESCANSO',
                'clock_out' => 'SALIDA',
                default => strtoupper((string) $direction),
            },
            'employee_id' => $employee->getKey(),
            'employee_name' => (string) $employee->name,
            'employee_number' => (string) ($employee->employee_number ?? ''),
            'clocked_at' => $clockedAt?->toIso8601String(),
            'time' => $clockedAt?->format('H:i:s'),
            'photo_saved' => (bool) $storedPhotoPath,
            'terminal' => [
                'id' => $terminal->getKey(),
                'uuid' => (string) $terminal->uuid,
                'code' => (string) $terminal->code,
                'name' => (string) $terminal->name,
                'company_id' => $terminal->company_id,
                'branch_id' => $terminal->branch_id,
            ],
        ];
    }

    public function preview(
        AttendanceTerminal $terminal,
        string $rawEmployeeQr,
    ): array {
        if (! $terminal->active || $terminal->isBlocked()) {
            throw ValidationException::withMessages([
                'terminal' => 'Esta terminal esta bloqueada o desactivada.',
            ]);
        }

        if (! $terminal->branch_id) {
            throw ValidationException::withMessages([
                'terminal' => 'La terminal no tiene una sucursal fisica asignada.',
            ]);
        }

        $employeeToken = $this->normalizeEmployeeToken($rawEmployeeQr);

        if ($employeeToken === '') {
            throw ValidationException::withMessages([
                'employee_qr' => 'La credencial QR no contiene un token valido.',
            ]);
        }

        $employee = Employee::query()
            ->with('company')
            ->where('attendance_qr_token', $employeeToken)
            ->where('attendance_qr_enabled', true)
            ->where('active', true)
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_qr' => 'Credencial no reconocida o desactivada.',
            ]);
        }

        if ((int) $employee->company_id !== (int) $terminal->company_id) {
            throw ValidationException::withMessages([
                'employee_qr' => 'Esta credencial no pertenece a la empresa autorizada para esta terminal.',
            ]);
        }

        if (
            $employee->company
            && isset($employee->company->attendance_qr_enabled)
            && ! (bool) $employee->company->attendance_qr_enabled
        ) {
            throw ValidationException::withMessages([
                'employee_qr' => 'El registro de asistencia por QR esta desactivado para esta empresa.',
            ]);
        }

        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('attendance_date', now()->toDateString())
            ->first();

        $mealBreaks = collect();
        $openMealBreak = null;
        $mealBreakCount = 0;

        if ($attendance?->getKey()) {
            $mealBreaks = EmployeeAttendanceBreak::query()
                ->where(
                    'employee_attendance_id',
                    $attendance->getKey()
                )
                ->where('break_type', 'meal')
                ->orderBy('started_at')
                ->orderBy('id')
                ->get();

            $openMealBreak = $mealBreaks
                ->first(
                    fn (EmployeeAttendanceBreak $break): bool =>
                        (bool) $break->started_at
                        && ! $break->ended_at
                );

            $mealBreakCount = $mealBreaks
                ->filter(
                    fn (EmployeeAttendanceBreak $break): bool =>
                        (bool) $break->started_at
                )
                ->count();
        }

        $actions = $this->allowedActions(
            $attendance,
            $openMealBreak,
            $mealBreakCount
        );

        $breakMinutesUsed = $mealBreaks->sum(
            fn (EmployeeAttendanceBreak $break): int =>
                (int) ($break->actualMinutes() ?? 0)
        );

        $breakMinutesAllowed = max(
            0,
            (int) ($attendance?->break_minutes ?? 0)
        );

        $breakSessionsAllowed = max(
            0,
            (int) ($attendance?->break_sessions_allowed ?? 0)
        );

        return [
            'employee_id' => $employee->getKey(),
            'employee_name' => (string) $employee->name,
            'employee_number' => (string) ($employee->employee_number ?? ''),
            'attendance_id' => $attendance?->getKey(),
            'complete' => (bool) ($attendance?->clock_out_at),
            'break_minutes' => $breakMinutesAllowed,
            'break_sessions_allowed' => $breakSessionsAllowed,
            'break_sessions_used' => $mealBreakCount,
            'break_minutes_used' => $breakMinutesUsed,
            'break_minutes_remaining' => max(
                0,
                $breakMinutesAllowed - $breakMinutesUsed
            ),
            'attendance' => [
                'clock_in' => $attendance?->clock_in_at?->format('H:i:s'),
                'meal_out' => $mealBreaks->first()?->started_at?->format('H:i:s'),
                'meal_in' => $mealBreaks->last()?->ended_at?->format('H:i:s'),
                'breaks' => $mealBreaks
                    ->values()
                    ->map(
                        fn (
                            EmployeeAttendanceBreak $break,
                            int $index
                        ): array => [
                            'number' => $index + 1,
                            'out' => $break->started_at?->format('H:i:s'),
                            'in' => $break->ended_at?->format('H:i:s'),
                            'minutes' => $break->actualMinutes(),
                            'open' => (bool) (
                                $break->started_at
                                && ! $break->ended_at
                            ),
                        ]
                    )
                    ->all(),
                'clock_out' => $attendance?->clock_out_at?->format('H:i:s'),
            ],
            'allowed_actions' => array_map(
                fn (string $action): array => [
                    'action' => $action,
                    'label' => $this->selectionLabel($action),
                ],
                $actions
            ),
        ];
    }

    protected function allowedActions(
        ?EmployeeAttendance $attendance,
        ?EmployeeAttendanceBreak $openMealBreak = null,
        int $mealBreakCount = 0,
    ): array {
        if (! $attendance || ! $attendance->clock_in_at) {
            return ['clock_in'];
        }

        if ($attendance->clock_out_at) {
            return [];
        }

        /*
         * Un descanso abierto siempre debe poder cerrarse.
         * También se permite terminar jornada; en ese caso se cierra
         * automáticamente con la misma hora.
         */
        if (
            $openMealBreak
            && $openMealBreak->started_at
            && ! $openMealBreak->ended_at
        ) {
            return ['meal_in', 'clock_out'];
        }

        $minutesAllowed = max(
            0,
            (int) ($attendance->break_minutes ?? 0)
        );

        $sessionsAllowed = max(
            0,
            (int) ($attendance->break_sessions_allowed ?? 0)
        );

        if ($minutesAllowed <= 0 || $sessionsAllowed <= 0) {
            return ['clock_out'];
        }

        if ($mealBreakCount < $sessionsAllowed) {
            return ['meal_out', 'clock_out'];
        }

        return ['clock_out'];
    }

    protected function selectionLabel(string $action): string
    {
        return match ($action) {
            'clock_in' => 'Registrar entrada',
            'meal_out' => 'Salir a descanso',
            'meal_in' => 'Regresar de descanso',
            'clock_out' => 'Terminar jornada',
            default => 'Registrar',
        };
    }

    public function normalizeEmployeeToken(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        if (preg_match('~/asistencia/empleado/([^/?#]+)~i', $raw, $matches)) {
            return trim(rawurldecode((string) $matches[1]));
        }

        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            $path = (string) parse_url($raw, PHP_URL_PATH);

            if (preg_match('~/asistencia/empleado/([^/?#]+)~i', $path, $matches)) {
                return trim(rawurldecode((string) $matches[1]));
            }
        }

        return $raw;
    }

    protected function storePhoto(
        UploadedFile $photo,
        AttendanceTerminal $terminal,
        Employee $employee,
        string $direction,
        \DateTimeInterface $clockedAt,
    ): string {
        $extension = match (strtolower((string) $photo->getMimeType())) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $directory = sprintf(
            'attendance-terminal/%s/%s/%s/company-%d/terminal-%d',
            $clockedAt->format('Y'),
            $clockedAt->format('m'),
            $clockedAt->format('d'),
            $terminal->company_id,
            $terminal->getKey(),
        );

        $filename = sprintf(
            '%s_employee-%d_%s_%s.%s',
            $clockedAt->format('His'),
            $employee->getKey(),
            match ($direction) {
                'clock_in' => 'in',
                'meal_out' => 'meal-out',
                'meal_in' => 'meal-in',
                'clock_out' => 'out',
                default => 'event',
            },
            Str::lower((string) Str::uuid()),
            $extension,
        );

        $path = $photo->storeAs($directory, $filename, 'local');

        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'photo' => 'No fue posible guardar la fotografia de evidencia.',
            ]);
        }

        return $path;
    }

    protected function appendNote(?string $notes, string $line): string
    {
        $notes = trim((string) $notes);
        $line = trim($line);

        return $notes === '' ? $line : $notes . PHP_EOL . $line;
    }
}
