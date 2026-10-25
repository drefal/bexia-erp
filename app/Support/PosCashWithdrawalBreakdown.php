<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PosCashWithdrawalBreakdown
{
    public static function forSession(int $sessionId): array
    {
        $result = [
            'withdrawal_count' => 0,
            'recorded_total' => 0.0,
            'detailed_total' => 0.0,
            'undetailed_total' => 0.0,
            'denominations' => [],
            'withdrawals' => [],
        ];

        if ($sessionId <= 0 ||
            !Schema::hasTable('pos_cash_movements')) {
            return $result;
        }

        $rows = DB::table('pos_cash_movements')
            ->where('pos_session_id', $sessionId)
            ->where('type', 'cash_out')
            ->orderBy('id')
            ->get();

        $aggregated = [];

        foreach ($rows as $movement) {
            $metadata = json_decode(
                (string) ($movement->metadata ?? ''),
                true
            );

            $metadata = is_array($metadata) ? $metadata : [];

            // Una devolucion no es un retiro de supervisor.
            if (
                ($metadata['source'] ?? '') ===
                    'pos_ticket_multimethod_refund'
                || !empty($metadata['pos_order_refund_id'])
                || !empty($metadata['refund_payment_ids'])
            ) {
                continue;
            }

            $amountCents = (int) round(
                (float) $movement->amount * 100
            );

            $result['withdrawal_count']++;
            $result['recorded_total'] += $amountCents / 100;

            $raw = $metadata['cash_denominations'] ?? null;
            $lines = [];
            $lineCents = 0;
            $valid = is_array($raw) && count($raw) > 0;

            if ($valid) {
                foreach ($raw as $line) {
                    if (!is_array($line)) {
                        $valid = false;
                        break;
                    }

                    $value = $line['value'] ?? null;
                    $quantity = $line['quantity'] ?? null;
                    $type = strtolower(
                        trim((string) ($line['type'] ?? ''))
                    );

                    if (
                        !is_numeric($value)
                        || !is_numeric($quantity)
                        || !is_finite((float) $value)
                        || !is_finite((float) $quantity)
                        || !in_array($type, ['bill', 'coin'], true)
                        || (float) $value <= 0
                        || (float) $quantity <= 0
                        || (float) $quantity > 100000
                        || floor((float) $quantity) !=
                            (float) $quantity
                    ) {
                        $valid = false;
                        break;
                    }

                    $valueCents = (int) round(
                        (float) $value * 100
                    );
                    $qty = (int) $quantity;
                    $subtotal = $valueCents * $qty;

                    $lineCents += $subtotal;

                    $lines[] = [
                        'type' => $type,
                        'value' => $valueCents / 100,
                        'quantity' => $qty,
                        'total' => $subtotal / 100,
                    ];
                }

                if ($lineCents !== $amountCents) {
                    $valid = false;
                }
            }

            if ($valid) {
                foreach ($lines as $line) {
                    $key = $line['type'] . '|'
                        . number_format(
                            $line['value'], 2, '.', ''
                        );

                    if (!isset($aggregated[$key])) {
                        $aggregated[$key] = [
                            'type' => $line['type'],
                            'value' => $line['value'],
                            'quantity' => 0,
                            'total' => 0.0,
                        ];
                    }

                    $aggregated[$key]['quantity'] +=
                        $line['quantity'];
                    $aggregated[$key]['total'] +=
                        $line['total'];
                }

                $result['detailed_total'] += $amountCents / 100;
            } else {
                $lines = [];
                $result['undetailed_total'] +=
                    $amountCents / 100;
            }

            $result['withdrawals'][] = [
                'id' => (int) $movement->id,
                'number' => (string) ($movement->number ?? ''),
                'movement_at' =>
                    (string) ($movement->movement_at ?? ''),
                'supervisor' =>
                    (string) ($movement->supervisor_name ?? ''),
                'reason' => (string) ($movement->reason ?? ''),
                'amount' => $amountCents / 100,
                'has_breakdown' => $valid,
                'denominations' => $lines,
            ];
        }

        $result['denominations'] = array_values($aggregated);

        usort(
            $result['denominations'],
            static function (array $a, array $b): int {
                $comparison = $b['value'] <=> $a['value'];

                if ($comparison !== 0) {
                    return $comparison;
                }

                return ($a['type'] === 'bill' ? 0 : 1)
                    <=> ($b['type'] === 'bill' ? 0 : 1);
            }
        );

        foreach ([
            'recorded_total',
            'detailed_total',
            'undetailed_total',
        ] as $key) {
            $result[$key] = round($result[$key], 2);
        }

        return $result;
    }
}
