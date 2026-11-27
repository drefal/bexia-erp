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
            'action' => [
                'nullable',
                'string',
                'in:preview,clock_in,meal_out,meal_in,clock_out',
            ],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
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

        /*
         * RRHH-SEG01:
         * Las terminales de Seguridad son de consulta.
         * No pueden registrar ni previsualizar asistencia
         * mediante el servicio del kiosco.
         */
        if (
            data_get(
                $terminal->settings,
                'terminal_type',
                'attendance'
            ) === 'security'
        ) {
            return response()->json([
                'ok' => false,
                'code' => 'security_terminal_clock_forbidden',
                'message' =>
                    'Esta terminal es exclusiva de Seguridad.',
            ], 403);
        }

        $action = strtolower(
            trim((string) ($data['action'] ?? 'preview'))
        );

        if ($action === '') {
            $action = 'preview';
        }

        try {
            if ($action === 'preview') {
                $result = $clock->preview(
                    terminal: $terminal,
                    rawEmployeeQr: (string) $data['employee_qr'],
                );

                return response()->json(array_merge([
                    'ok' => true,
                    'mode' => 'preview',
                    'message' => 'Selecciona el registro a realizar.',
                    'server_time' => now()->toIso8601String(),
                ], $result));
            }

            $photo = $request->file('photo');

            if (! $photo) {
                return response()->json([
                    'ok' => false,
                    'code' => 'photo_required',
                    'message' => 'Se requiere fotografia para registrar la asistencia.',
                ], 422);
            }

            $result = $clock->register(
                terminal: $terminal,
                rawEmployeeQr: (string) $data['employee_qr'],
                requestedAction: $action,
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

        /*
         * RRHH-SEG01: notificar solamente entradas
         * registradas correctamente.
         */
        if (($result['direction'] ?? null) === 'clock_in') {
            try {
                app(
                    \App\Support\Attendance\AttendanceRealtimePublisher::class
                )->publishEntry($terminal);
            } catch (\Throwable $e) {
                try {
                    report($e);
                } catch (\Throwable) {
                    // El marcaje ya fue guardado.
                }
            }
        }

        return response()->json(array_merge([
            'ok' => true,
            'mode' => 'registered',
            'message' => 'Registro correcto.',
            'server_time' => now()->toIso8601String(),
        ], $result));
    }
}
