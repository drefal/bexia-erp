<?php

namespace App\Support\ComputerRental;

use App\Models\ComputerRentalRate;
use App\Models\ComputerRentalSession;
use App\Models\ComputerRentalStation;
use Illuminate\Http\Request;
use App\Support\ComputerRental\ComputerRentalAgentCommandService;

class ComputerRentalAgentService
{
    public function authenticate(
        string $uuid,
        string $token
    ): ?ComputerRentalStation {
        $uuid = trim($uuid);
        $token = trim($token);

        if ($uuid === '' || $token === '') {
            return null;
        }

        $station = ComputerRentalStation::query()
            ->where('agent_uuid', $uuid)
            ->where('is_active', true)
            ->first();

        if (
            ! $station
            || blank($station->agent_token_hash)
        ) {
            return null;
        }

        if (! hash_equals(
            (string) $station->agent_token_hash,
            hash('sha256', $token)
        )) {
            return null;
        }

        return $station;
    }

    public function heartbeat(
        ComputerRentalStation $station,
        Request $request,
        array $data
    ): array {
        $station->forceFill([
            'last_heartbeat_at' => now(),

            'agent_status' =>
                'online',

            'agent_hostname' =>
                $this->clean(
                    $data['hostname'] ?? null,
                    255
                ),

            'agent_machine_guid' =>
                $this->clean(
                    $data['machine_guid'] ?? null,
                    255
                ),

            'agent_version' =>
                $this->clean(
                    $data['agent_version'] ?? null,
                    80
                ),

            'agent_windows_user' =>
                $this->clean(
                    $data['windows_user'] ?? null,
                    255
                ),

            'agent_last_ip' =>
                mb_substr(
                    (string) $request->ip(),
                    0,
                    64
                ),

            'agent_last_error' =>
                $this->clean(
                    $data['last_error'] ?? null,
                    4000
                ),
        ])->save();

        $session = ComputerRentalSession::query()
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
            ->orderByDesc('id')
            ->first();

        return [
            'ok' => true,

            'server_time' =>
                now()->toIso8601String(),

            'station' => [
                'id' =>
                    (int) $station->id,

                'company_id' =>
                    (int) $station->company_id,

                'code' =>
                    (string) $station->code,

                'name' =>
                    (string) $station->name,

                'status' =>
                    (string) $station->status,

                'agent_status' =>
                    'online',

                'last_heartbeat_at' =>
                    now()->toIso8601String(),
            ],

            'rental' =>
                $session
                    ? $this->rentalPayload(
                        $session
                    )
                    : null,

            'command' =>
                $this->commandPayload(
                    $station
                ),
        ];
    }

    protected function commandPayload(
        ComputerRentalStation $station
    ): ?array {
        $commands =
            app(
                ComputerRentalAgentCommandService::class
            );

        $command =
            $commands->nextForStation(
                $station
            );

        if (! $command) {
            return null;
        }

        $payload =
            $commands->payload(
                $command
            );

        /*
         * La primera entrega cambia pending -> delivered.
         *
         * Mientras no exista ACK, una orden delivered
         * puede volver a incluirse en un heartbeat.
         * El UUID del comando permite al agente aplicar
         * su protección local contra duplicados.
         */
        $commands->markDelivered(
            $command
        );

        return $payload;
    }

    protected function rentalPayload(
        ComputerRentalSession $session
    ): array {
        $snapshotRate =
            new ComputerRentalRate([
                'billing_mode' =>
                    $session->billing_mode,

                'hourly_rate' =>
                    $session->hourly_rate,

                'minimum_minutes' =>
                    $session->minimum_minutes,

                'billing_increment_minutes' =>
                    $session
                        ->billing_increment_minutes,

                'prepaid_minutes' =>
                    $session->prepaid_minutes,

                'prepaid_price' =>
                    $session->prepaid_price,

                'tax_rate' =>
                    $session->tax_rate,
            ]);

        $calculation =
            app(
                ComputerRentalCalculator::class
            )->calculate(
                $snapshotRate,
                $session->started_at,
                now()
            );

        $elapsedSeconds = max(
            0,
            (int) floor(
                (float) $calculation[
                    'duration_seconds'
                ]
            )
        );

        $payload = [
            'session_id' =>
                (int) $session->id,

            'status' =>
                (string) $session->status,

            'billing_mode' =>
                (string) $session->billing_mode,

            'started_at' =>
                $session->started_at
                    ? $session
                        ->started_at
                        ->toIso8601String()
                    : null,

            'elapsed_seconds' =>
                $elapsedSeconds,

            'billable_minutes' =>
                (int) $calculation[
                    'billable_minutes'
                ],

            'estimated_amount' =>
                round(
                    (float) $calculation[
                        'amount'
                    ],
                    2
                ),
        ];

        if (
            (string) $session->billing_mode
            === 'prepaid'
            && (int) $session->prepaid_minutes > 0
        ) {
            $totalSeconds =
                ((int) $session->prepaid_minutes)
                * 60;

            $payload['prepaid_minutes'] =
                (int) $session->prepaid_minutes;

            $payload['remaining_seconds'] =
                max(
                    0,
                    $totalSeconds
                    - $elapsedSeconds
                );
        }

        return $payload;
    }

    protected function clean(
        mixed $value,
        int $max
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $max
        );
    }
}
