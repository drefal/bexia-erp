<?php

namespace App\Support\ComputerRental;

use App\Models\ComputerRentalRate;
use App\Models\ComputerRentalSession;
use App\Models\ComputerRentalStation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComputerRentalSessionService
{
    public function start(
        ComputerRentalStation $station,
        ComputerRentalRate $rate,
        ?int $userId = null
    ): ComputerRentalSession {
        if (! $station->is_active) {
            throw ValidationException::withMessages([
                'station' => 'La estación está desactivada.',
            ]);
        }

        if ($station->status !== 'available') {
            throw ValidationException::withMessages([
                'station' => 'La estación no está disponible.',
            ]);
        }

        if (! $rate->is_active) {
            throw ValidationException::withMessages([
                'rate' => 'La tarifa está desactivada.',
            ]);
        }

        if ((int) $station->company_id !== (int) $rate->company_id) {
            throw ValidationException::withMessages([
                'rate' => 'La tarifa pertenece a otra empresa.',
            ]);
        }

        return DB::transaction(function () use ($station, $rate, $userId) {
            $lockedStation = ComputerRentalStation::query()
                ->lockForUpdate()
                ->findOrFail($station->id);

            $active = ComputerRentalSession::query()
                ->where('station_id', $lockedStation->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();

            if ($active || $lockedStation->status !== 'available') {
                throw ValidationException::withMessages([
                    'station' => 'La estación ya tiene una renta activa.',
                ]);
            }

            $session = ComputerRentalSession::create([
                'company_id' => $lockedStation->company_id,
                'station_id' => $lockedStation->id,
                'rate_id' => $rate->id,
                'pos_point_id' => $lockedStation->pos_point_id,
                'opened_by_user_id' => $userId,
                'status' => 'active',

                'billing_mode' => $rate->billing_mode,
                'hourly_rate' => $rate->hourly_rate,
                'minimum_minutes' => $rate->minimum_minutes,
                'billing_increment_minutes' => $rate->billing_increment_minutes,
                'cancellation_grace_minutes' =>
                    (int) ($rate->cancellation_grace_minutes ?? 5),
                'prepaid_minutes' => $rate->prepaid_minutes,
                'prepaid_price' => $rate->prepaid_price,
                'tax_rate' => $rate->tax_rate,

                'product_id' => $rate->product_id,

                'started_at' => now(),

                'duration_seconds' => 0,
                'billable_minutes' => 0,
                'amount' => 0,

                'metadata' => [
                    'source' => 'computer_rental_panel',
                    'station_code' => $lockedStation->code,
                    'station_name' => $lockedStation->name,
                    'rate_name' => $rate->name,
                ],
            ]);

            $lockedStation->update([
                'status' => 'in_use',
            ]);

            return $session->fresh();
        });
    }

    public function finish(
        ComputerRentalSession $session,
        ?int $userId = null
    ): ComputerRentalSession {
        return DB::transaction(function () use ($session, $userId) {
            $lockedSession = ComputerRentalSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($lockedSession->status !== 'active') {
                throw ValidationException::withMessages([
                    'session' => 'La renta ya no está activa.',
                ]);
            }

            $rate = ComputerRentalRate::query()
                ->findOrFail($lockedSession->rate_id);

            /*
             * Se usa el snapshot guardado en la sesión.
             * Así un cambio posterior de tarifa no altera el cobro.
             */
            $snapshotRate = new ComputerRentalRate([
                'billing_mode' => $lockedSession->billing_mode,
                'hourly_rate' => $lockedSession->hourly_rate,
                'minimum_minutes' => $lockedSession->minimum_minutes,
                'billing_increment_minutes' => $lockedSession->billing_increment_minutes,
                'prepaid_minutes' => $lockedSession->prepaid_minutes,
                'prepaid_price' => $lockedSession->prepaid_price,
                'tax_rate' => $lockedSession->tax_rate,
            ]);

            $endedAt = now();

            $calculation = app(ComputerRentalCalculator::class)
                ->calculate(
                    $snapshotRate,
                    $lockedSession->started_at,
                    $endedAt
                );

            $lockedSession->update([
                'status' => 'pending_pos',
                'ended_at' => $endedAt,
                'closed_by_user_id' => $userId,
                /*
                 * CIBER3G1A_DURATION_SECONDS_INT
                 *
                 * Carbon puede entregar segundos con fracción.
                 * La columna computer_rental_sessions.duration_seconds
                 * es BIGINT, por lo que siempre guardamos segundos enteros.
                 */
                'duration_seconds' => (int) floor(
                    (float) $calculation['duration_seconds']
                ),
                'billable_minutes' => (int) $calculation['billable_minutes'],
                'amount' => $calculation['amount'],
            ]);

            ComputerRentalStation::query()
                ->where('id', $lockedSession->station_id)
                ->update([
                    'status' => 'available',
                    'updated_at' => now(),
                ]);

            return $lockedSession->fresh();
        });
    }

    /**
     * CIBER3H2B_GRACE_CANCEL
     *
     * Cancelación sin cobro:
     * - solamente mientras la renta está activa;
     * - dentro del periodo snapshot de cortesía;
     * - sin consumos adicionales registrados;
     * - conserva duración real y motivo para auditoría;
     * - importe y minutos facturables quedan en cero.
     */
    public function cancel(
        ComputerRentalSession $session,
        string $reason,
        ?int $userId = null
    ): ComputerRentalSession {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancel_reason' =>
                    'Indica el motivo de cancelación.',
            ]);
        }

        return DB::transaction(
            function () use (
                $session,
                $reason,
                $userId
            ) {
                $lockedSession =
                    ComputerRentalSession::query()
                        ->lockForUpdate()
                        ->findOrFail($session->id);

                if ($lockedSession->status !== 'active') {
                    throw ValidationException::withMessages([
                        'session' =>
                            'La renta ya no está activa.',
                    ]);
                }

                $graceMinutes = max(
                    0,
                    (int) (
                        $lockedSession
                            ->cancellation_grace_minutes
                        ?? 5
                    )
                );

                $startedTimestamp =
                    $lockedSession->started_at
                        ? $lockedSession
                            ->started_at
                            ->timestamp
                        : now()->timestamp;

                $endedAt = now();

                $elapsedSeconds = max(
                    0,
                    (int) (
                        $endedAt->timestamp
                        - $startedTimestamp
                    )
                );

                $graceSeconds =
                    $graceMinutes * 60;

                if (
                    $graceMinutes <= 0
                    || $elapsedSeconds > $graceSeconds
                ) {
                    throw ValidationException::withMessages([
                        'session' =>
                            'El periodo de cortesía de '
                            . $graceMinutes
                            . ' minuto(s) ya terminó. '
                            . 'Finaliza la renta para cobrarla normalmente.',
                    ]);
                }

                $consumptionCount =
                    \App\Models\ComputerRentalSessionLine::query()
                        ->where(
                            'computer_rental_session_id',
                            $lockedSession->id
                        )
                        ->count();

                if ($consumptionCount > 0) {
                    throw ValidationException::withMessages([
                        'session' =>
                            'Esta cuenta ya tiene consumos. '
                            . 'Quítalos primero o finaliza la renta para cobrarlos.',
                    ]);
                }

                $metadata =
                    is_array($lockedSession->metadata)
                        ? $lockedSession->metadata
                        : [];

                $metadata['free_cancellation'] = [
                    'without_charge' => true,
                    'grace_minutes' =>
                        $graceMinutes,
                    'elapsed_seconds' =>
                        $elapsedSeconds,
                    'cancelled_by_user_id' =>
                        $userId,
                    'cancelled_at' =>
                        $endedAt->toISOString(),
                    'reason' =>
                        $reason,
                ];

                $lockedSession->update([
                    'status' => 'cancelled',
                    'ended_at' => $endedAt,
                    'closed_by_user_id' => $userId,
                    'cancelled_at' => $endedAt,
                    'cancel_reason' => $reason,
                    'duration_seconds' =>
                        $elapsedSeconds,
                    'billable_minutes' => 0,
                    'amount' => 0,
                    'metadata' => $metadata,
                ]);

                ComputerRentalStation::query()
                    ->where(
                        'id',
                        $lockedSession->station_id
                    )
                    ->update([
                        'status' => 'available',
                        'updated_at' => now(),
                    ]);

                return $lockedSession->fresh();
            }
        );
    }
}
