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

                if ($attendance && $attendance->clock_in_at && $attendance->clock_out_at) {
                    throw ValidationException::withMessages([
                        'employee_qr' => 'Ya tienes entrada y salida registradas para hoy.',
                    ]);
                }

                if ($attendance && $attendance->clock_in_at && ! $attendance->clock_out_at) {
                    $secondsSinceEntry = $attendance->clock_in_at->diffInSeconds($clockedAt);

                    if ($secondsSinceEntry < self::DUPLICATE_WINDOW_SECONDS) {
                        throw ValidationException::withMessages([
                            'employee_qr' => 'Registro duplicado. Espera un momento antes de volver a pasar tu tarjeta.',
                        ]);
                    }

                    $direction = 'clock_out';
                } else {
                    $direction = 'clock_in';
                }

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

                $prefix = $direction === 'clock_out' ? 'clock_out' : 'clock_in';
                $deviceFingerprint = hash('sha256', 'attendance-terminal|' . (string) $terminal->uuid);
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

                if ($direction === 'clock_in') {
                    $attendance->forceFill(array_merge([
                        'company_id' => $employee->company_id,
                        'employee_id' => $employee->getKey(),
                        'attendance_date' => $today,
                        'clock_in_at' => $clockedAt,
                        'source' => 'terminal',
                        'notes' => $this->appendNote(
                            $attendance->notes,
                            'Entrada registrada en terminal ' . $terminal->code . ' / ' . $terminal->name . '.'
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
                            'Salida registrada en terminal ' . $terminal->code . ' / ' . $terminal->name . '.'
                        ),
                        'updated_by_user_id' => null,
                    ], $evidence));
                }

                $attendance->save();
            }, 3);
        } catch (\Throwable $e) {
            if ($storedPhotoPath) {
                Storage::disk('local')->delete($storedPhotoPath);
            }

            throw $e;
        }

        if ($direction === 'clock_out' && $attendance) {
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
            'direction_label' => $direction === 'clock_out' ? 'SALIDA' : 'ENTRADA',
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
            $direction === 'clock_out' ? 'out' : 'in',
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
