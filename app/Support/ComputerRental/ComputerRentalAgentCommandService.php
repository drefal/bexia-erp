<?php

namespace App\Support\ComputerRental;

use App\Models\ComputerRentalAgentCommand;
use App\Models\ComputerRentalSession;
use App\Models\ComputerRentalStation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComputerRentalAgentCommandService
{
    public const TYPE_SHUTDOWN = 'shutdown';
    public const TYPE_RESTART = 'restart';
    public const TYPE_START_UI = 'start_ui';

    public const ALLOWED_TYPES = [
        self::TYPE_SHUTDOWN,
        self::TYPE_RESTART,
        self::TYPE_START_UI,
    ];

    public function queue(
        ComputerRentalStation $station,
        string $type,
        ?int $requestedByUserId
    ): ComputerRentalAgentCommand {
        if (! in_array(
            $type,
            self::ALLOWED_TYPES,
            true
        )) {
            throw ValidationException::withMessages([
                'command' =>
                    'Comando remoto no permitido.',
            ]);
        }

        $activeRental =
            ComputerRentalSession::query()
                ->where(
                    'company_id',
                    (int) $station->company_id
                )
                ->where(
                    'station_id',
                    (int) $station->id
                )
                ->where(
                    'status',
                    'active'
                )
                ->exists();

        if (
            $activeRental
            && in_array(
                $type,
                [
                    self::TYPE_SHUTDOWN,
                    self::TYPE_RESTART,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'command' =>
                    'No se puede apagar o reiniciar una PC con una renta activa.',
            ]);
        }

        $alreadyPending =
            ComputerRentalAgentCommand::query()
                ->where(
                    'station_id',
                    (int) $station->id
                )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'delivered',
                    ]
                )
                ->exists();

        if ($alreadyPending) {
            throw ValidationException::withMessages([
                'command' =>
                    'La estación ya tiene un comando remoto pendiente.',
            ]);
        }

        return ComputerRentalAgentCommand::query()
            ->create([
                'company_id' =>
                    (int) $station->company_id,

                'station_id' =>
                    (int) $station->id,

                'command_uuid' =>
                    (string) Str::uuid(),

                'command_type' =>
                    $type,

                'status' =>
                    'pending',

                'requested_by_user_id' =>
                    $requestedByUserId,

                'requested_at' =>
                    now(),

                'metadata' => [
                    'source' =>
                        'computer_rental_control',
                ],
            ]);
    }

    public function nextForStation(
        ComputerRentalStation $station
    ): ?ComputerRentalAgentCommand {
        return ComputerRentalAgentCommand::query()
            ->where(
                'company_id',
                (int) $station->company_id
            )
            ->where(
                'station_id',
                (int) $station->id
            )
            ->whereIn(
                'status',
                [
                    'pending',
                    'delivered',
                ]
            )
            ->orderBy('id')
            ->first();
    }

    public function markDelivered(
        ComputerRentalAgentCommand $command
    ): void {
        if (
            (string) $command->status
            !== 'pending'
        ) {
            return;
        }

        $command->forceFill([
            'status' =>
                'delivered',

            'delivered_at' =>
                now(),
        ])->save();
    }

    public function acknowledgeForStation(
        ComputerRentalStation $station,
        string $commandUuid,
        string $status,
        ?string $error = null
    ): ?ComputerRentalAgentCommand {
        if (! in_array(
            $status,
            [
                'acknowledged',
                'failed',
            ],
            true
        )) {
            return null;
        }

        $command =
            ComputerRentalAgentCommand::query()
                ->where(
                    'company_id',
                    (int) $station->company_id
                )
                ->where(
                    'station_id',
                    (int) $station->id
                )
                ->where(
                    'command_uuid',
                    $commandUuid
                )
                ->first();

        if (! $command) {
            return null;
        }

        if ($status === 'acknowledged') {
            /*
             * Idempotente:
             * repetir ACK del mismo UUID no genera
             * una nueva orden ni duplica ejecución.
             */
            if (
                (string) $command->status
                !== 'acknowledged'
            ) {
                $command->forceFill([
                    'status' =>
                        'acknowledged',

                    'acknowledged_at' =>
                        now(),

                    'failed_at' =>
                        null,

                    'error_message' =>
                        null,
                ])->save();
            }

            return $command->refresh();
        }

        $command->forceFill([
            'status' =>
                'failed',

            'failed_at' =>
                now(),

            'error_message' =>
                $error
                    ? mb_substr(
                        trim($error),
                        0,
                        4000
                    )
                    : 'El agente reportó un fallo.',
        ])->save();

        return $command->refresh();
    }

    public function payload(
        ComputerRentalAgentCommand $command
    ): array {
        return [
            'id' =>
                (string) $command->command_uuid,

            'type' =>
                (string) $command->command_type,

            'requested_at' =>
                $command->requested_at
                    ? $command->requested_at
                        ->toIso8601String()
                    : null,
        ];
    }
}
