<?php

namespace App\Support;

final class PosCloseReconciliation
{
    public static function methodKey(string $label): string
    {
        $s = mb_strtolower(\Illuminate\Support\Str::ascii($label));
        if (str_contains($s, 'efectivo') || str_contains($s, 'cash')) return 'cash';
        if (str_contains($s, 'transfer') || str_contains($s, 'spei')) return 'transfer';
        if (str_contains($s, 'debito') || str_contains($s, 'debit')) return 'debit';
        if (str_contains($s, 'credito') || str_contains($s, 'credit')) return 'credit';
        if (str_contains($s, 'tarjeta') || str_contains($s, 'card')) return 'other_card';
        return 'other';
    }

    public static function calculate(array $summary, ?array $actual = null, ?float $cashCounted = null): array
    {
        // BEXIA_RECON9N2D_FROZEN_SNAPSHOT
        // La conciliacion de una sesion cerrada no cambia
        // aunque una devolucion bancaria sea confirmada despues.
        $closeSession = $summary['session'] ?? [];

        if ((string)($closeSession['status'] ?? '') === 'closed') {
            $saved = $closeSession['close_reconciliation'] ?? null;

            if (is_string($saved)) {
                $saved = json_decode($saved, true);
            }

            if (
                is_array($saved)
                && isset(
                    $saved['registered'],
                    $saved['actual'],
                    $saved['recorded_total'],
                    $saved['reconciled_total'],
                    $saved['difference']
                )
                && is_array($saved['registered'])
                && is_array($saved['actual'])
            ) {
                return $saved;
            }
        }

        $totals = $summary['totals'] ?? [];
        $byMethod = ['cash'=>0., 'credit'=>0., 'debit'=>0., 'other_card'=>0., 'transfer'=>0., 'other'=>0.];
        $rows = $summary['payments_by_method'] ?? [];
        foreach ($rows as $row) {
            $key = self::methodKey((string) ($row['method'] ?? ''));
            $byMethod[$key] += (float) ($row['total'] ?? 0);
        }
        foreach ($byMethod as $k=>$v) $byMethod[$k] = round($v, 2);
        $cashIn = round((float)($totals['cash_in_total'] ?? 0), 2);
        $cashOutGross = round((float)($totals['cash_out_total'] ?? 0), 2);
        $refunds = [
            'cash'=>0., 'credit'=>0., 'debit'=>0.,
            'other_card'=>0., 'transfer'=>0., 'other'=>0.
        ];
        $pendingRefunds = $refunds;
        $cashRefundMovement = 0.0;
        $sessionId = (int)($summary['session']['id'] ?? 0);

        // BEXIA_RECON9N2A_EXECUTION_SESSION
        // La sesion de ejecucion se identifica por pago.
        // La cabecera de la devolucion no se modifica.
        $executionSession = null;

        if ($sessionId > 0
            && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            $executionSession = \Illuminate\Support\Facades\DB::table(
                'pos_sessions'
            )
                ->where('id', $sessionId)
                ->first(['id', 'company_id', 'pos_point_id']);
        }

        if ($executionSession !== null
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')) {

            $payments = \Illuminate\Support\Facades\DB::table(
                'pos_order_refund_payments as rp'
            )
                ->join('pos_order_refunds as r',
                    'r.id', '=', 'rp.pos_order_refund_id')
                ->where('r.company_id', (int)$executionSession->company_id)
                ->where('r.pos_point_id', (int)$executionSession->pos_point_id)
                ->whereNotIn('r.status',
                    ['cancelled','canceled','void'])
                ->select(
                    'rp.payment_label',
                    'rp.amount',
                    'rp.status',
                    'rp.metadata',
                    'r.pos_session_id as header_session_id'
                )
                ->get();

            foreach ($payments as $payment) {
                $meta = json_decode(
                    (string)($payment->metadata ?? ''),
                    true
                );
                $meta = is_array($meta) ? $meta : [];

                // La sesion explicita del pago tiene prioridad.
                // En registros antiguos sin ella, usar la cabecera.
                $assignedSessionId = !empty($meta['refund_session_id'])
                    ? (int)$meta['refund_session_id']
                    : (int)($payment->header_session_id ?? 0);

                if ($assignedSessionId !== $sessionId) {
                    continue;
                }

                $key = self::methodKey(
                    (string)($payment->payment_label ?? '')
                );
                $amount = round((float)$payment->amount, 2);
                if ($amount <= 0) continue;

                $status = strtolower(
                    (string)($payment->status ?? '')
                );
                $settlement = strtolower(
                    (string)($meta['settlement_status'] ?? '')
                );

                if ($key === 'cash') {
                    $confirmed = $status === 'refunded'
                        && (
                            $settlement === 'cash_paid'
                            || (
                                $settlement === ''
                                && empty($meta['external_refund_confirmed'])
                            )
                        );
                } else {
                    $confirmed = $status === 'refunded'
                        && (
                            $settlement === 'externally_confirmed'
                            || !empty($meta['external_refund_confirmed'])
                            || (
                                $settlement === ''
                                && empty($meta['refund_session_id'])
                            )
                        );
                }

                if ($confirmed) {
                    $refunds[$key] += $amount;
                } else {
                    $pendingRefunds[$key] += $amount;
                }
            }
        }

        if ($sessionId > 0
            && \Illuminate\Support\Facades\Schema::hasTable('pos_cash_movements')) {

            $movements = \Illuminate\Support\Facades\DB::table(
                'pos_cash_movements'
            )
                ->where('pos_session_id', $sessionId)
                ->where('type', 'cash_out')
                ->get(['amount','metadata']);

            foreach ($movements as $movement) {
                $meta = json_decode(
                    (string)($movement->metadata ?? ''),
                    true
                );
                $meta = is_array($meta) ? $meta : [];

                if (
                    ($meta['source'] ?? '') === 'pos_ticket_multimethod_refund'
                    || !empty($meta['pos_order_refund_id'])
                    || !empty($meta['refund_payment_ids'])
                ) {
                    $cashRefundMovement += (float)$movement->amount;
                }
            }
        }

        $cashRefundMovement = round($cashRefundMovement, 2);
        $cashOut = round(
            max(0, $cashOutGross - $cashRefundMovement), 2
        );

        foreach ($refunds as $key => $amount) {
            $refunds[$key] = round($amount, 2);
            $pendingRefunds[$key] = round($pendingRefunds[$key], 2);
        }

        $netByMethod = $byMethod;
        foreach ($netByMethod as $key => $amount) {
            $netByMethod[$key] = round(
                $amount - $refunds[$key], 2
            );
        }
        $opening = round((float)($totals['opening_cash_amount'] ?? 0), 2);
        $declared = [];
        foreach (['credit','debit','other_card','transfer','other'] as $key) {
            $value = $actual[$key] ?? $netByMethod[$key];
            $declared[$key] = round((float)$value,2);
        }
        $counted = round((float)($cashCounted ?? ($summary['session']['closing_amount'] ?? 0)),2);
        $recorded = round(array_sum($netByMethod),2);
        // Formula solicitada: contado + retiros - entradas + no efectivo real.
        $reconciled = round($counted + $cashOut - $cashIn + array_sum($declared),2);
        return [
            'registered'=>$netByMethod,
            'gross_registered'=>$byMethod,
            'refunds_paid'=>$refunds,
            'refunds_pending'=>$pendingRefunds,
            'actual'=>$declared,
            'cash_counted'=>$counted,
            'cash_in'=>$cashIn,
            'cash_out'=>$cashOut,
            'cash_out_gross'=>$cashOutGross,
            'cash_refund_movements'=>$cashRefundMovement,
            'opening'=>$opening,'recorded_total'=>$recorded,
            'reconciled_total'=>$reconciled,'difference'=>round($reconciled-$recorded,2),
        ];
    }

    public static function validateActual(mixed $value): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        if (!is_array($value)) throw \Illuminate\Validation\ValidationException::withMessages(['close_payment_actual'=>'Importes reales inválidos.']);
        $clean=[];
        foreach (['credit','debit','other_card','transfer','other'] as $key) {
            if (!array_key_exists($key,$value)) continue;
            $v=$value[$key];
            if (!is_numeric($v) || !is_finite((float)$v) || (float)$v < -999999999 || (float)$v > 999999999) {
                throw \Illuminate\Validation\ValidationException::withMessages(['close_payment_actual'=>'Importe real inválido: '.$key]);
            }
            $clean[$key]=round((float)$v,2);
        }
        return $clean;
    }
}
