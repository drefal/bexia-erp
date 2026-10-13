<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosSessionRefundDetail
{
    // BEXIA_V5836_POS_REFUND_FIX2B8P9D2
    // Desglose informativo: no modifica saldo, caja ni totales.
    public static function build(int $sessionId): array
    {
        $empty = [
            'rows' => [],
            'total' => 0.0,
            'cash' => 0.0,
            'external' => 0.0,
            'pending' => 0.0,
        ];

        if (
            $sessionId <= 0
            || ! Schema::hasTable('pos_sessions')
            || ! Schema::hasTable('pos_order_refunds')
            || ! Schema::hasTable('pos_order_refund_payments')
            || ! Schema::hasTable('pos_orders')
        ) {
            return $empty;
        }

        $session = DB::table('pos_sessions')
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            return $empty;
        }

        $refunds = DB::table('pos_order_refunds as r')
            ->leftJoin('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
            ->where('r.pos_session_id', $sessionId)
            ->where('r.company_id', (int) $session->company_id)
            ->where('r.pos_point_id', (int) $session->pos_point_id)
            ->whereNotIn('r.status', ['cancelled', 'canceled', 'void'])
            ->orderBy('r.created_at')
            ->orderBy('r.id')
            ->get([
                'r.id',
                'r.number',
                'r.type',
                'r.status',
                'r.payment_total',
                'r.created_at',
                'r.created_by_user_id',
                'o.number as ticket_number',
            ]);

        if ($refunds->isEmpty()) {
            return $empty;
        }

        $payments = DB::table('pos_order_refund_payments')
            ->whereIn('pos_order_refund_id', $refunds->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('pos_order_refund_id');

        $userNames = Schema::hasTable('users')
            ? DB::table('users')
                ->whereIn(
                    'id',
                    $refunds->pluck('created_by_user_id')->filter()->unique()
                )
                ->pluck('name', 'id')
            : collect();

        $rows = [];
        $total = 0;
        $cash = 0;
        $external = 0;
        $pending = 0;

        foreach ($refunds as $refund) {
            $refundCents = (int) round(
                abs((float) $refund->payment_total) * 100
            );
            $total += $refundCents;

            $items = [];

            foreach (
                $payments->get((int) $refund->id, collect())
                as $payment
            ) {
                $amount = (int) round(
                    abs((float) $payment->amount) * 100
                );

                $method = (string) ($payment->payment_label ?? '');
                $status = (string) ($payment->status ?? '');

                $meta = json_decode(
                    (string) ($payment->metadata ?? '{}'),
                    true
                );
                $meta = is_array($meta) ? $meta : [];

                $isCash = (int) ($payment->payment_form_id ?? 0) === 7
                    || str_contains(
                        mb_strtolower($method),
                        'efectivo'
                    );

                if ($status === 'pending') {
                    $pending += $amount;
                } elseif ($isCash) {
                    $cash += $amount;
                } else {
                    $external += $amount;
                }

                $items[] = [
                    'method' => $method ?: 'Sin metodo',
                    'amount' => $amount / 100,
                    'status' => $status,
                    'confirmed_at' => (string) (
                        $meta['bank_confirmed_at'] ?? ''
                    ),
                    'confirmed_by' => (string) (
                        $meta['bank_confirmed_by_user_id'] ?? ''
                    ),
                ];
            }

            $rows[] = [
                'id' => (int) $refund->id,
                'number' => (string) $refund->number,
                'ticket_number' => (string) (
                    $refund->ticket_number ?? ''
                ),
                'created_at' => (string) $refund->created_at,
                'user' => (string) (
                    $userNames->get($refund->created_by_user_id)
                    ?: 'Usuario #' . (
                        $refund->created_by_user_id ?? 'N/D'
                    )
                ),
                'type' => (string) $refund->type,
                'amount' => $refundCents / 100,
                'payments' => $items,
            ];
        }

        return [
            'rows' => $rows,
            'total' => $total / 100,
            'cash' => $cash / 100,
            'external' => $external / 100,
            'pending' => $pending / 100,
        ];
    }
}
