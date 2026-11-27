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
}
