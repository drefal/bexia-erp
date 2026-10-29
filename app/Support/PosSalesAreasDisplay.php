<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BEXIA_V5836_POS_AREAS3J
final class PosSalesAreasDisplay
{
    public static function resolve(
        int $sessionId,
        object $pos,
        array $summary
    ): array {
        if (
            ! Schema::hasColumn(
                'pos_points',
                'sales_breakdown_mode'
            )
        ) {
            return [];
        }

        $mode = strtolower(trim(
            (string) ($pos->sales_breakdown_mode ?? 'default')
        ));

        $status = (string) (
            $summary['session']['status'] ?? ''
        );

        $snapshot = $summary['session'][
            'papelon_close_snapshot'
        ] ?? null;

        /*
         * BEXIA_V5836_POS_AREAS3I
         *
         * Sesiones cerradas:
         * nunca recalcular las areas usando categorias actuales.
         */
        if ($status === 'closed') {
            if (
                is_array($snapshot)
                && (int) ($snapshot['version'] ?? 0) === 2
                && ($snapshot['breakdown_mode'] ?? '') === 'areas'
                && is_array($snapshot['sections'] ?? null)
            ) {
                return [
                    'sections' => self::rectifyHistorical145(
                        $sessionId,
                        $pos,
                        $snapshot
                    ),
                    'products_by_section' =>
                        $snapshot['products_by_section'] ?? [],
                ];
            }

            return [];
        }

        if ($mode !== 'areas') {
            return [];
        }

        $companyId = (int) ($pos->company_id ?? 0);

        if (
            $companyId <= 0
            || ! Schema::hasColumn(
                'companies',
                'pos_sales_areas'
            )
        ) {
            return [];
        }

        $raw = DB::table('companies')
            ->where('id', $companyId)
            ->value('pos_sales_areas');

        $areas = is_array($raw)
            ? $raw
            : json_decode((string) ($raw ?? '[]'), true);

        if (! is_array($areas) || $areas === []) {
            return [];
        }

        $hasValidArea = false;

        foreach ($areas as $area) {
            if (
                is_array($area)
                && trim((string) ($area['name'] ?? '')) !== ''
                && ! empty($area['category_ids'])
            ) {
                $hasValidArea = true;
                break;
            }
        }

        if (! $hasValidArea) {
            return [];
        }

        $data = PosPapelonCloseSummary::build($sessionId, true);

        if (! ($data['ok'] ?? false)) {
            return [];
        }

        return [
            'sections' => $data['sections'] ?? [],
            'products_by_section' =>
                $data['products_by_section'] ?? [],
        ];
    }

    /*
     * BEXIA_V5836_PAPELON_HISTORICAL145_VIEW_FIX
     *
     * Rectificacion visual exclusiva de la sesion 145.
     * El snapshot original no se modifica.
     * Si falla cualquier validacion, se muestra el original.
     */
    private static function rectifyHistorical145(
        int $sessionId,
        object $pos,
        array $snapshot
    ): array {
        $original = $snapshot['sections'] ?? [];

        if (
            $sessionId !== 145
            || (int) ($pos->company_id ?? 0) !== 3
            || (int) ($pos->id ?? 0) !== 2
            || (int) ($snapshot['version'] ?? 0) !== 2
            || ($snapshot['breakdown_mode'] ?? '') !== 'areas'
            || ! is_array($original)
        ) {
            return $original;
        }

        try {
            $expected = [
                'PAPELÓN' => 406050,
                'IMPRESIÓN Y COPIAS' => 164600,
                'MAGICTOYS' => 170100,
                'JUGUETES' => 65800,
            ];

            if (count($original) !== count($expected)) {
                return $original;
            }

            $seen = [];
            $cashFound = false;

            foreach ($original as $section) {
                $name = (string) ($section['name'] ?? '');
                $cents = (int) round(
                    (float) ($section['total'] ?? 0) * 100
                );

                if (
                    ! array_key_exists($name, $expected)
                    || isset($seen[$name])
                    || $expected[$name] !== $cents
                ) {
                    return $original;
                }

                $seen[$name] = true;

                if ($name === 'MAGICTOYS') {
                    foreach ($section['methods'] ?? [] as $method) {
                        if (
                            ($method['method'] ?? '') === 'Efectivo'
                            && (int) round(
                                (float) ($method['total'] ?? 0) * 100
                            ) === 38700
                        ) {
                            $cashFound = true;
                        }
                    }
                }
            }

            if (!$cashFound || count($seen) !== 4) {
                return $original;
            }

            $refunds = DB::table('pos_order_refunds')
                ->where('company_id', 3)
                ->where('pos_point_id', 2)
                ->where('pos_session_id', 145)
                ->where('status', 'done')
                ->whereIn('id', [60, 61])
                ->get(['id', 'payment_total']);

            if ($refunds->count() !== 2) {
                return $original;
            }

            $refundCents = 0;

            foreach ($refunds as $refund) {
                $id = (int) $refund->id;

                $lines = DB::table('pos_order_refund_lines as rl')
                    ->join(
                        'products as p',
                        'p.id',
                        '=',
                        'rl.product_id'
                    )
                    ->where('rl.pos_order_refund_id', $id)
                    ->get([
                        'rl.total',
                        'p.product_category_id',
                    ]);

                $payments = DB::table('pos_order_refund_payments')
                    ->where('pos_order_refund_id', $id)
                    ->get([
                        'amount',
                        'status',
                        'payment_form_id',
                    ]);

                if ($lines->isEmpty() || $payments->isEmpty()) {
                    return $original;
                }

                $lineCents = 0;
                $paymentCents = 0;

                foreach ($lines as $line) {
                    if ((int) $line->product_category_id !== 758) {
                        return $original;
                    }

                    $lineCents += (int) round(
                        abs((float) $line->total) * 100
                    );
                }

                foreach ($payments as $payment) {
                    if (
                        (string) $payment->status !== 'refunded'
                        || (int) $payment->payment_form_id !== 7
                    ) {
                        return $original;
                    }

                    $paymentCents += (int) round(
                        abs((float) $payment->amount) * 100
                    );
                }

                $documentCents = (int) round(
                    abs((float) $refund->payment_total) * 100
                );

                if (
                    $lineCents !== $documentCents
                    || $paymentCents !== $documentCents
                ) {
                    return $original;
                }

                $refundCents += $paymentCents;
            }

            if ($refundCents !== 8000) {
                return $original;
            }

            $result = $original;

            foreach ($result as &$section) {
                if (($section['name'] ?? '') !== 'MAGICTOYS') {
                    continue;
                }

                $section['total'] = 1621.00;

                foreach ($section['methods'] as &$method) {
                    if (($method['method'] ?? '') === 'Efectivo') {
                        $method['total'] = 307.00;
                    }
                }
                unset($method);
            }
            unset($section);

            return $result;
        } catch (\Throwable $e) {
            return $original;
        }
    }

}
