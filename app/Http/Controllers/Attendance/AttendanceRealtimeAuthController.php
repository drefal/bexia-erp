<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Support\Attendance\AttendanceRealtimePublisher;
use App\Support\Attendance\AttendanceTerminalPairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AttendanceRealtimeAuthController extends Controller
{
    public function store(
        Request $request,
        AttendanceTerminalPairingService $pairing
    ): JsonResponse {
        if (! config('attendance_realtime.enabled')) {
            abort(503);
        }

        $data = $request->validate([
            'socket_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^\d+\.\d+$/D',
            ],
        ]);

        $uuid = trim((string) $request->header(
            'X-Bexia-Terminal-UUID'
        ));

        $token = trim((string) $request->bearerToken());

        if ($uuid === '' || $token === '') {
            abort(401);
        }

        $terminal = $pairing->authenticateTerminal(
            $uuid,
            $token,
            $request
        );

        if (! $terminal) {
            abort(401);
        }

        if (
            ! $terminal->isAvailable()
            || data_get(
                $terminal->settings,
                'terminal_type',
                'attendance'
            ) !== 'security'
            || (int) $terminal->company_id <= 0
            || (int) $terminal->branch_id <= 0
        ) {
            abort(403);
        }

        // No aceptar empresa, sucursal o canal
        // seleccionados por el navegador.

        $channel = AttendanceRealtimePublisher::channel(
            (int) $terminal->company_id,
            (int) $terminal->branch_id
        );

        $key = (string) config(
            'attendance_realtime.key'
        );

        $secret = (string) config(
            'attendance_realtime.secret'
        );

        if ($key === '' || $secret === '') {
            abort(503);
        }

        $signature = hash_hmac(
            'sha256',
            $data['socket_id'] . ':' . $channel,
            $secret
        );

        return response()->json([
            'channel' => $channel,
            'auth' => $key . ':' . $signature,
        ])->header('Cache-Control', 'no-store');
    }
}
