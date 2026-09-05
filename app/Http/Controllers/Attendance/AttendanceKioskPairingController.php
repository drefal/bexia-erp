<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Support\Attendance\AttendanceTerminalPairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceKioskPairingController extends Controller
{
    public function show(): View
    {
        return view('attendance.kiosk');
    }

    public function requestPairing(
        Request $request,
        AttendanceTerminalPairingService $pairing,
    ): JsonResponse {
        $data = $request->validate([
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($pairing->createPairingRequest($data));
    }

    public function pairingStatus(
        Request $request,
        AttendanceTerminalPairingService $pairing,
    ): JsonResponse {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'exchange_secret' => ['required', 'string', 'size:64'],
        ]);

        $result = $pairing->pairingStatus(
            (string) $data['request_id'],
            (string) $data['exchange_secret'],
            $request,
        );

        return match ($result['state'] ?? 'expired') {
            'pending' => response()->json($result, 202),
            'paired' => response()->json($result),
            'blocked' => response()->json($result, 403),
            default => response()->json([
                'state' => 'expired',
                'message' => 'La solicitud vencio. Genera un codigo nuevo.',
            ], 410),
        };
    }

    public function terminalStatus(
        Request $request,
        AttendanceTerminalPairingService $pairing,
    ): JsonResponse {
        $uuid = trim((string) ($request->header('X-Bexia-Terminal-UUID') ?: $request->input('terminal_uuid')));
        $token = trim((string) ($request->bearerToken() ?: $request->input('terminal_token')));

        if ($uuid === '' || $token === '') {
            return response()->json([
                'state' => 'unauthorized',
                'message' => 'La tablet no tiene credenciales de terminal.',
            ], 401);
        }

        $terminal = $pairing->authenticateTerminal($uuid, $token, $request);

        if (! $terminal) {
            return response()->json([
                'state' => 'unauthorized',
                'message' => 'La vinculacion de esta tablet ya no es valida.',
            ], 401);
        }

        if (! $terminal->active) {
            return response()->json([
                'state' => 'inactive',
                'message' => 'Esta terminal esta desactivada.',
            ], 403);
        }

        if ($terminal->isBlocked()) {
            return response()->json([
                'state' => 'blocked',
                'message' => $terminal->blocked_reason ?: 'Esta terminal esta bloqueada.',
            ], 403);
        }

        return response()->json([
            'state' => 'ready',
            'terminal' => $pairing->terminalPayload($terminal),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
