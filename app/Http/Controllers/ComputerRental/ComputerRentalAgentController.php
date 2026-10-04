<?php

namespace App\Http\Controllers\ComputerRental;

use App\Http\Controllers\Controller;
use App\Support\ComputerRental\ComputerRentalAgentCommandService;
use App\Support\ComputerRental\ComputerRentalAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComputerRentalAgentController extends Controller
{
    public function heartbeat(
        Request $request,
        ComputerRentalAgentService $agents
    ): JsonResponse {
        $data = $request->validate([
            'hostname' => [
                'nullable',
                'string',
                'max:255',
            ],

            'machine_guid' => [
                'nullable',
                'string',
                'max:255',
            ],

            'agent_version' => [
                'nullable',
                'string',
                'max:80',
            ],

            'windows_user' => [
                'nullable',
                'string',
                'max:255',
            ],

            'last_error' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ]);

        $station =
            $this->authenticateAgent(
                $request,
                $agents
            );

        if ($station instanceof JsonResponse) {
            return $station;
        }

        return response()->json(
            $agents->heartbeat(
                $station,
                $request,
                $data
            )
        );
    }

    public function commandAck(
        Request $request,
        ComputerRentalAgentService $agents,
        ComputerRentalAgentCommandService $commands
    ): JsonResponse {
        $data = $request->validate([
            'command_id' => [
                'required',
                'uuid',
            ],

            'status' => [
                'required',
                'string',
                'in:acknowledged,failed',
            ],

            'error' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ]);

        $station =
            $this->authenticateAgent(
                $request,
                $agents
            );

        if ($station instanceof JsonResponse) {
            return $station;
        }

        $command =
            $commands->acknowledgeForStation(
                $station,
                (string) $data['command_id'],
                (string) $data['status'],
                $data['error'] ?? null
            );

        if (! $command) {
            return response()->json(
                [
                    'ok' => false,
                    'code' =>
                        'command_not_found',
                    'message' =>
                        'La orden no pertenece a esta estación.',
                ],
                404
            );
        }

        return response()->json([
            'ok' => true,

            'command' => [
                'id' =>
                    (string) $command->command_uuid,

                'type' =>
                    (string) $command->command_type,

                'status' =>
                    (string) $command->status,

                'acknowledged_at' =>
                    $command->acknowledged_at
                        ? $command->acknowledged_at
                            ->toIso8601String()
                        : null,

                'failed_at' =>
                    $command->failed_at
                        ? $command->failed_at
                            ->toIso8601String()
                        : null,
            ],
        ]);
    }

    protected function authenticateAgent(
        Request $request,
        ComputerRentalAgentService $agents
    ): mixed {
        $uuid = trim(
            (string) (
                $request->header(
                    'X-Bexia-Station-UUID'
                )
                ?: $request->input(
                    'station_uuid'
                )
            )
        );

        $token = trim(
            (string) (
                $request->bearerToken()
                ?: $request->input(
                    'station_token'
                )
            )
        );

        if (
            $uuid === ''
            || $token === ''
        ) {
            return response()->json(
                [
                    'ok' => false,
                    'code' =>
                        'agent_credentials_missing',
                    'message' =>
                        'Faltan credenciales del agente.',
                ],
                401
            );
        }

        $station =
            $agents->authenticate(
                $uuid,
                $token
            );

        if (! $station) {
            return response()->json(
                [
                    'ok' => false,
                    'code' =>
                        'agent_unauthorized',
                    'message' =>
                        'Credenciales de agente no válidas.',
                ],
                401
            );
        }

        return $station;
    }
}
