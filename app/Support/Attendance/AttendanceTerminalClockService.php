<?php

namespace App\Support\Attendance;

use App\Models\AttendanceTerminal;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
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

        if (
            $employee->company
            && isset($employee->company->attendance_qr_enabled)
            && ! (bool) $employee->company->attendance_qr_enabled
        ) {
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
                // Serializa lecturas simultaneas del mismo empleado.
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

                if ($attendance && $attendance->clock_out_at) {
                    throw ValidationException::withMessages([
                        'employee_qr' => 'Ya tienes todos los registros de asistencia requeridos para hoy.',
                    ]);
                }

                if ($attendance) {
                    $lastPunchAt = $this->latestPunchAt($attendance);

                    if ($lastPunchAt) {
                        $secondsSinceLastPunch = $lastPunchAt->diffInSeconds($clockedAt);

                        if ($secondsSinceLastPunch < self::DUPLICATE_WINDOW_SECONDS) {
                            throw ValidationException::withMessages([
                                'employee_qr' => 'Registro duplicado. Espera un momento antes de volver a pasar tu tarjeta.',
                            ]);
                        }
                    }

                }

                $requestedAction = strtolower(trim($requestedAction));
                $allowedActions = $this->allowedActions($attendance);

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

                $prefix = $direction;

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

                switch ($direction) {
                    case 'clock_in':
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
                        break;

                    case 'meal_out':
                        $attendance->forceFill(array_merge([
                            'meal_out_at' => $clockedAt,
                            'source' => $attendance->source ?: 'terminal',
                            'notes' => $this->appendNote(
                                $attendance->notes,
                                'Salida a comida registrada en terminal '
                                    . $terminal->code
                                    . ' / '
                                    . $terminal->name
                                    . '.'
                            ),
                            'updated_by_user_id' => null,
                        ], $evidence));
                        break;

                    case 'meal_in':
                        $attendance->forceFill(array_merge([
                            'meal_in_at' => $clockedAt,
                            'source' => $attendance->source ?: 'terminal',
                            'notes' => $this->appendNote(
                                $attendance->notes,
                                'Regreso de comida registrado en terminal '
                                    . $terminal->code
                                    . ' / '
                                    . $terminal->name
                                    . '.'
                            ),
                            'updated_by_user_id' => null,
                        ], $evidence));
                        break;

                    case 'clock_out':
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
                        break;

                    default:
                        throw ValidationException::withMessages([
                            'employee_qr' => 'No fue posible determinar el tipo de registro.',
                        ]);
                }

                $attendance->save();

                // V5.83.4c9:
                // la terminal tambien conserva el snapshot de si la
                // jornada requiere marcas de comida.
                if ($direction === 'clock_in') {
                    $attendance->forceFill([
                        'meal_punch_required' =>
                            (int) ($attendance->break_minutes ?? 0) > 0,
                    ])->save();
                }
            }, 3);
        } catch (\Throwable $e) {
            if ($storedPhotoPath) {
                Storage::disk('local')->delete($storedPhotoPath);
            }

            throw $e;
        }

        // Las incidencias automaticas se sincronizan unicamente
        // cuando se registra la salida final del dia.
        if ($direction === 'clock_out' && $attendance) {
            try {
                EmployeeAttendanceIncidentSync::syncAll(
                    $attendance->fresh(),
                    null,
                    true
                );
            } catch (\Throwable $e) {
                report($e);

                try {
                    $attendance->forceFill([
                        'notes' => $this->appendNote(
                            $attendance->notes,
                            'No se pudo generar incidencia automatica desde terminal: '
                                . $e->getMessage()
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
            'direction_label' => $this->directionLabel((string) $direction),
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
                'employee_qr' =>
                    'Esta credencial no pertenece a la empresa autorizada para esta terminal.',
            ]);
        }

        if (
            $employee->company
            && isset($employee->company->attendance_qr_enabled)
            && ! (bool) $employee->company->attendance_qr_enabled
        ) {
            throw ValidationException::withMessages([
                'employee_qr' =>
                    'El registro de asistencia por QR esta desactivado para esta empresa.',
            ]);
        }

        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('attendance_date', now()->toDateString())
            ->first();

        $actions = $this->allowedActions($attendance);

        return [
            'employee_id' => $employee->getKey(),
            'employee_name' => (string) $employee->name,
            'employee_number' =>
                (string) ($employee->employee_number ?? ''),
            'attendance_id' => $attendance?->getKey(),
            'complete' => (bool) ($attendance?->clock_out_at),
            'break_minutes' =>
                (int) ($attendance?->break_minutes ?? 0),
            'attendance' => [
                'clock_in' =>
                    $attendance?->clock_in_at?->format('H:i:s'),
                'meal_out' =>
                    $attendance?->meal_out_at?->format('H:i:s'),
                'meal_in' =>
                    $attendance?->meal_in_at?->format('H:i:s'),
                'clock_out' =>
                    $attendance?->clock_out_at?->format('H:i:s'),
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
        ?EmployeeAttendance $attendance
    ): array {
        if (! $attendance || ! $attendance->clock_in_at) {
            return ['clock_in'];
        }

        if ($attendance->clock_out_at) {
            return [];
        }

        if ((int) ($attendance->break_minutes ?? 0) <= 0) {
            return ['clock_out'];
        }

        if (! $attendance->meal_out_at) {
            return ['meal_out', 'clock_out'];
        }

        if (! $attendance->meal_in_at) {
            return ['meal_in', 'clock_out'];
        }

        return ['clock_out'];
    }

    protected function selectionLabel(string $action): string
    {
        return match ($action) {
            'clock_in' => 'Registrar entrada',
            'meal_out' => 'Salir a comer',
            'meal_in' => 'Regresar de comida',
            'clock_out' => 'Terminar jornada',
            default => 'Registrar',
        };
    }
    protected function latestPunchAt(EmployeeAttendance $attendance)
    {
        $punches = array_values(array_filter([
            $attendance->clock_in_at,
            $attendance->meal_out_at,
            $attendance->meal_in_at,
            $attendance->clock_out_at,
        ]));

        if ($punches === []) {
            return null;
        }

        usort(
            $punches,
            static fn ($a, $b): int => $a->getTimestamp() <=> $b->getTimestamp()
        );

        return $punches[array_key_last($punches)];
    }

    protected function directionLabel(string $direction): string
    {
        return match ($direction) {
            'clock_in' => 'ENTRADA',
            'meal_out' => 'SALIDA A COMIDA',
            'meal_in' => 'REGRESO DE COMIDA',
            'clock_out' => 'SALIDA',
            default => 'REGISTRO',
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

        $directionSlug = match ($direction) {
            'clock_in' => 'in',
            'meal_out' => 'meal-out',
            'meal_in' => 'meal-in',
            'clock_out' => 'out',
            default => 'event',
        };

        $filename = sprintf(
            '%s_employee-%d_%s_%s.%s',
            $clockedAt->format('His'),
            $employee->getKey(),
            $directionSlug,
            Str::lower((string) Str::uuid()),
            $extension,
        );

        $path = $photo->storeAs(
            $directory,
            $filename,
            'local'
        );

        if (
            ! is_string($path)
            || $path === ''
            || ! Storage::disk('local')->exists($path)
        ) {
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

        return $notes === ''
            ? $line
            : $notes . PHP_EOL . $line;
    }
}
