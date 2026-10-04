<?php

namespace App\Support\ComputerRental;

use App\Models\ComputerRentalSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComputerRentalPosStatusService
{
    public function syncCompany(int $companyId): array
    {
        if (
            $companyId <= 0
            || ! Schema::hasTable('computer_rental_sessions')
            || ! Schema::hasTable('pos_orders')
        ) {
            return [
                'checked' => 0,
                'paid' => 0,
            ];
        }

        $sessions = ComputerRentalSession::query()
            ->where('company_id', $companyId)
            ->whereNotNull('pos_order_id')
            ->whereIn('status', [
                'pending_pos',
                'sent_to_pos',
                'paid',
                'ticket_cancelled',
            ])
            ->orderBy('id')
            ->get();

        $checked = 0;
        $paid = 0;

        /** CIBER3H1_SYNC_CANCELLED */
        $cancelled = 0;

        foreach ($sessions as $session) {
            $checked++;

            $order = DB::table('pos_orders')
                ->where('id', (int) $session->pos_order_id)
                ->where('company_id', $companyId)
                ->first();

            if (! $order) {
                continue;
            }

            $orderStatus =
                strtolower(
                    trim(
                        (string) ($order->status ?? '')
                    )
                );

            /*
             * CIBER3H1
             *
             * Un ticket cancelado NO reabre la renta ni vuelve la PC a En uso.
             * Conservamos el ticket cancelado como trazabilidad y marcamos
             * explícitamente la sesión.
             */
            $isCancelled = in_array(
                $orderStatus,
                ['cancelled', 'canceled'],
                true
            );

            if ($isCancelled) {
                if ($session->status !== 'ticket_cancelled') {
                    $metadata =
                        is_array($session->metadata)
                            ? $session->metadata
                            : [];

                    $orderMetadata = [];

                    if (! empty($order->metadata)) {
                        $decoded = json_decode(
                            (string) $order->metadata,
                            true
                        );

                        if (is_array($decoded)) {
                            $orderMetadata = $decoded;
                        }
                    }

                    $metadata['pos_ticket_cancelled'] = [
                        'source' => 'pos_order',
                        'pos_order_id' => (int) $order->id,
                        'pos_order_number' =>
                            (string) ($order->number ?? ''),
                        'pos_status' =>
                            (string) ($order->status ?? ''),
                        'cancelled_at' =>
                            $order->cancelled_at
                                ?? ($orderMetadata['cancelled_at'] ?? null),
                        'cancelled_by_user_id' =>
                            $orderMetadata['cancelled_by_user_id']
                                ?? null,
                        'cancel_reason' =>
                            $orderMetadata['cancel_reason']
                                ?? null,
                        'cancel_source' =>
                            $orderMetadata['cancel_source']
                                ?? null,
                        'synced_at' =>
                            now()->toISOString(),
                    ];

                    $session->update([
                        'status' => 'ticket_cancelled',
                        'metadata' => $metadata,
                    ]);

                    $cancelled++;
                }

                continue;
            }

            $isPaid =
                $orderStatus === 'paid'
                || ! empty($order->paid_at);

            if (! $isPaid) {
                continue;
            }

            if (
                $session->status !== 'paid'
                || empty($session->paid_at)
            ) {
                $metadata =
                    is_array($session->metadata)
                        ? $session->metadata
                        : [];

                $metadata['payment_sync'] = [
                    'source' => 'pos_order',
                    'pos_order_id' => (int) $order->id,
                    'pos_order_number' =>
                        (string) ($order->number ?? ''),
                    'pos_status' =>
                        (string) ($order->status ?? ''),
                    'synced_at' =>
                        now()->toISOString(),
                ];

                $session->update([
                    'status' => 'paid',
                    'paid_at' =>
                        $order->paid_at
                            ?: now(),
                    'metadata' => $metadata,
                ]);

                $paid++;
            }
        }

        return [
            'checked' => $checked,
            'paid' => $paid,
            'cancelled' => $cancelled,
        ];
    }
}
