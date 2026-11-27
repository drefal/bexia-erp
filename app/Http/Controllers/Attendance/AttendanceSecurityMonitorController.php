<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use App\Support\Attendance\AttendanceTerminalPairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Support\Attendance\AttendanceAccessMonitorPresentation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceSecurityMonitorController extends Controller
{
    public function show(): View
    {
        // La pagina publica no contiene datos de empleados.
        // Los datos requieren autenticacion de terminal.
        return view('attendance.security-monitor');
    }

    public function entries(
        Request $request,
        AttendanceTerminalPairingService $pairing
    ): JsonResponse {
        $uuid = trim((string)
            $request->header('X-Bexia-Terminal-UUID')
        );

        $token = trim((string)
            $request->bearerToken()
        );

        if ($uuid === '' || $token === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Terminal no vinculada.',
            ], 401);
        }

        $terminal = $pairing->authenticateTerminal(
            $uuid,
            $token,
            $request
        );

        if (! $terminal) {
            return response()->json([
                'ok' => false,
                'message' => 'Vinculacion no valida.',
            ], 401);
        }

        if (! $terminal->active || $terminal->isBlocked()) {
            return response()->json([
                'ok' => false,
                'message' => 'Terminal desactivada o bloqueada.',
            ], 403);
        }

        if (
            data_get(
                $terminal->settings,
                'terminal_type',
                'attendance'
            ) !== 'security'
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Terminal no autorizada para Seguridad.',
            ], 403);
        }

        if (
            (int) $terminal->company_id <= 0
            || (int) $terminal->branch_id <= 0
        ) {
            abort(403);
        }

        $start = now('America/Mexico_City')
            ->startOfDay()
            ->utc();

        $attendances = EmployeeAttendance::query()
            ->with(['employee'])
            ->where('company_id', $terminal->company_id)
            ->whereNotNull(
                'clock_in_attendance_terminal_id'
            )
            ->whereRaw(
                "clock_in_device_info::jsonb ->> 'physical_company_id' = ?",
                [(string) $terminal->company_id]
            )
            ->whereRaw(
                "clock_in_device_info::jsonb ->> 'physical_branch_id' = ?",
                [(string) $terminal->branch_id]
            )
            ->whereNotNull('clock_in_at')
            ->where('clock_in_at', '>=', $start)
            ->orderByDesc('clock_in_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $decisions = DB::table('hr_access_decisions')
            ->where('company_id', $terminal->company_id)
            ->where('branch_id', $terminal->branch_id)
            ->whereIn(
                'employee_attendance_id',
                $attendances->modelKeys()
            )
            ->get([
                'employee_attendance_id',
                'employee_id',
                'attendance_terminal_id',
                'status',
                'reason_code',
            ])
            ->keyBy('employee_attendance_id');

        $rows = $attendances
            ->filter(fn ($a) =>
                $a->employee !== null
                && (int) $a->employee->company_id
                    === (int) $terminal->company_id
            )
            ->map(function ($attendance) use ($decisions): array {
                $employee = $attendance->employee;

                $decision = $decisions->get(
                    $attendance->id
                );

                if (
                    $decision
                    && (
                        (int) $decision->employee_id
                            !== (int) $employee->id
                        || (int) $decision->attendance_terminal_id
                            !== (int) $attendance->clock_in_attendance_terminal_id
                    )
                ) {
                    $decision = null;
                }

                return array_merge([
                    'id' => $attendance->id,
                    'employee_id' => $employee->id,
                    'employee_number' =>
                        (string) ($employee->employee_number ?? ''),
                    'name' => (string) $employee->name,
                    'department' =>
                        (string) ($employee->department ?? ''),
                    'photo' => $employee->avatar_url,
                    'has_entry_photo' => filled(
                        $attendance->clock_in_photo_path
                    ),
                    'clock_in' => $attendance->clock_in_at
                        ?->timezone('America/Mexico_City')
                        ->format('H:i:s'),
                ], AttendanceAccessMonitorPresentation::present(
                    $decision
                ));
            })
            ->values();

        return response()->json([
            'ok' => true,
            'terminal' => [
                'name' => $terminal->name,
                'company' => $terminal->company?->name,
                'branch' => $terminal->branch?->name,
            ],
            'server_time' => now()->toIso8601String(),
            'entries' => $rows,
        ])->header('Cache-Control', 'no-store');
    }

    // BEXIA_RRHH_SEG05_PRIVATE_PHOTO
    public function entryPhoto(
        Request $request,
        AttendanceTerminalPairingService $pairing,
        int $attendance
    ) {
        $uuid = trim((string)
            $request->header('X-Bexia-Terminal-UUID')
        );
        $token = trim((string) $request->bearerToken());

        abort_if($uuid === '' || $token === '', 401);

        $terminal = $pairing->authenticateTerminal(
            $uuid,
            $token,
            $request
        );

        abort_unless($terminal, 401);

        abort_unless(
            $terminal->active && !$terminal->isBlocked(),
            403
        );

        abort_unless(
            data_get(
                $terminal->settings,
                'terminal_type',
                'attendance'
            ) === 'security',
            403
        );

        abort_unless(
            (int) $terminal->company_id > 0
            && (int) $terminal->branch_id > 0,
            403
        );

        $start = now('America/Mexico_City')
            ->startOfDay()
            ->utc();

        $record = EmployeeAttendance::query()
            ->with('employee')
            ->where('company_id', $terminal->company_id)
            ->whereNotNull('clock_in_attendance_terminal_id')
            ->whereRaw(
                "clock_in_device_info::jsonb ->> 'physical_company_id' = ?",
                [(string) $terminal->company_id]
            )
            ->whereRaw(
                "clock_in_device_info::jsonb ->> 'physical_branch_id' = ?",
                [(string) $terminal->branch_id]
            )
            ->whereNotNull('clock_in_at')
            ->where('clock_in_at', '>=', $start)
            ->whereKey($attendance)
            ->firstOrFail();

        abort_unless(
            $record->employee !== null
            && (int) $record->employee->company_id
                === (int) $terminal->company_id,
            404
        );

        $path = (string) $record->clock_in_photo_path;

        $pattern = sprintf(
            '~\Aattendance-terminal/\d{4}/\d{2}/\d{2}/company-%d/terminal-%d/\d{6}_employee-%d_in_[a-f0-9-]{36}\.(jpg|png|webp)\z~i',
            $terminal->company_id,
            $record->clock_in_attendance_terminal_id,
            $record->employee_id
        );

        abort_unless(
            preg_match($pattern, $path) === 1,
            404
        );

        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path);

        abort_unless(
            in_array(
                $mime,
                ['image/jpeg', 'image/png', 'image/webp'],
                true
            ),
            404
        );

        // BEXIA_RRHH_SEG05_PRIVATE_CACHE
        $response = response()->file(
            $disk->path($path),
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'",
                'Referrer-Policy' => 'no-referrer',
            ]
        );

        $response->setPrivate();

        $response->headers->set(
            'Cache-Control',
            'private, no-store, no-cache, max-age=0, must-revalidate'
        );

        $response->headers->set(
            'Pragma',
            'no-cache'
        );

        $response->headers->set(
            'Expires',
            'Thu, 01 Jan 1970 00:00:00 GMT'
        );

        return $response;
    }

}
