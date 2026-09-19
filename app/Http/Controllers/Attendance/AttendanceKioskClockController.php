<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Support\Attendance\AttendanceTerminalClockService;
use App\Support\Attendance\AttendanceTerminalPairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceKioskClockController extends Controller
{
    public function store(
        Request $request,
        AttendanceTerminalPairingService $pairing,
        AttendanceTerminalClockService $clock,
    ): JsonResponse {
        $data = $request->validate([
            'terminal_uuid' => ['nullable', 'uuid'],
            'terminal_token' => ['nullable', 'string', 'min:32', 'max:255'],
            'employee_qr' => ['required', 'string', 'max:2048'],
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $uuid = trim((string) ($request->header('X-Bexia-Terminal-UUID') ?: ($data['terminal_uuid'] ?? '')));
        $token = trim((string) ($request->bearerToken() ?: ($data['terminal_token'] ?? '')));

        if ($uuid === '' || $token === '') {
            return response()->json([
                'ok' => false,
                'code' => 'terminal_credentials_missing',
                'message' => 'La tablet no tiene credenciales de terminal.',
            ], 401);
        }

        $terminal = $pairing->authenticateTerminal($uuid, $token, $request);

        if (! $terminal) {
            return response()->json([
                'ok' => false,
                'code' => 'terminal_unauthorized',
                'message' => 'La vinculacion de esta tablet ya no es valida.',
            ], 401);
        }

        if (! $terminal->active || $terminal->isBlocked()) {
            return response()->json([
                'ok' => false,
                'code' => 'terminal_blocked',
                'message' => $terminal->blocked_reason ?: 'Esta terminal esta bloqueada o desactivada.',
            ], 403);
        }

        try {
            $result = $clock->register(
                terminal: $terminal,
                rawEmployeeQr: (string) $data['employee_qr'],
                photo: $request->file('photo'),
                ipAddress: (string) $request->ip(),
                userAgent: (string) $request->userAgent(),
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->filter()->first()
                ?: 'No fue posible registrar la asistencia.';

            return response()->json([
                'ok' => false,
                'code' => 'validation_error',
                'message' => $message,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'code' => 'clock_error',
                'message' => 'No fue posible registrar la asistencia. Intenta nuevamente.',
            ], 500);
        }

        return response()->json(array_merge([
            'ok' => true,
            'message' => 'Registro correcto.',
            'server_time' => now()->toIso8601String(),
        ], $result));
    }
}
