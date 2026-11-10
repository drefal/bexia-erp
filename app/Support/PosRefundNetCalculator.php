<?php

namespace App\Support;

final class PosRefundNetCalculator
{
    private static function cents($value): int
    {
        if (! is_numeric($value)) {
            throw new \RuntimeException('Importe no numerico.');
        }

        return (int) round((float) $value * 100);
    }

    private static function protected(object $line): bool
    {
        if ((bool) ($line->locked ?? false)) {
            return true;
        }

        $meta = $line->metadata ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }
        if (is_object($meta)) {
            $meta = (array) $meta;
        }

        $raw = is_array($meta) ? ($meta['raw'] ?? []) : [];
        if (! is_array($raw)) {
            return false;
        }

        return ($raw['source'] ?? null) === 'computer_rental'
            && in_array($raw['source_type'] ?? null, [
                'rental',
                'consumption',
                'rental_resend',
                'consumption_resend',
            ], true);
    }

    public static function calculate(
        object $order,
        array $lines,
        array $quantities,
        bool $refundAll = false,
        ?int $paidCents = null
    ): array {
        if (! $lines) {
            throw new \RuntimeException('Ticket sin lineas.');
        }

        $meta = $order->metadata ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }
        if (is_object($meta)) {
            $meta = (array) $meta;
        }

        $discount = is_array($meta)
            ? ($meta['discount'] ?? null)
            : null;

        $gross = 0;
        $discountable = 0;
        $protectedTotal = 0;
        $rows = [];
        $seen = [];

        foreach ($lines as $line) {
            $id = (int) ($line->id ?? 0);
            $sold = (float) ($line->quantity ?? 0);

            if ($id <= 0 || isset($seen[$id]) || $sold <= 0) {
                throw new \RuntimeException('Linea invalida.');
            }
            $seen[$id] = true;

            $lineGross = self::cents($line->total ?? (
                $sold * (float) ($line->unit_price ?? 0)
            ));

            if ($lineGross < 0) {
                throw new \RuntimeException('Importe negativo.');
            }

            $isProtected = self::protected($line);
            $gross += $lineGross;

            if ($isProtected) {
                $protectedTotal += $lineGross;
            } else {
                $discountable += $lineGross;
            }

            $rows[$id] = [
                'id' => $id,
                'sold' => $sold,
                'gross_cents' => $lineGross,
                'protected' => $isProtected,
                'discount_cents' => 0,
            ];
        }

        $net = self::cents($order->total ?? null);
        if ($net <= 0 || $gross <= 0) {
            throw new \RuntimeException('Totales invalidos.');
        }

        if ($paidCents !== null && $paidCents !== $net) {
            throw new \RuntimeException(
                'Los pagos no concilian con el ticket.'
            );
        }

        $difference = $gross - $net;

        if ($discount === null) {
            if ($difference !== 0) {
                throw new \RuntimeException(
                    'Diferencia sin descuento documentado.'
                );
            }
        } else {
            if (! is_array($discount)
                || ! isset($discount['amount'])
                || ! is_numeric($discount['amount'])) {
                throw new \RuntimeException(
                    'Descuento sin importe verificable.'
                );
            }

            $documented = self::cents($discount['amount']);

            if ($difference < 0
                || abs($difference - $documented) > 1
                || $difference > $discountable) {
                throw new \RuntimeException(
                    'Descuento no conciliado.'
                );
            }

            if (isset($discount['protected_total'])
                && abs(
                    self::cents($discount['protected_total'])
                    - $protectedTotal
                ) > 1) {
                throw new \RuntimeException(
                    'Importe protegido no conciliado.'
                );
            }

            if (isset($discount['discountable_total'])
                && abs(
                    self::cents($discount['discountable_total'])
                    - $discountable
                ) > 1) {
                throw new \RuntimeException(
                    'Base descontable no conciliada.'
                );
            }

            if ($difference > 0 && $discountable > 0) {
                $allocated = 0;
                $remainders = [];

                foreach ($rows as $id => &$row) {
                    if ($row['protected']) {
                        continue;
                    }
                    $numerator = $difference
                        * $row['gross_cents'];
                    $base = intdiv(
                        $numerator,
                        $discountable
                    );
                    $row['discount_cents'] = $base;
                    $allocated += $base;
                    $remainders[] = [
                        'id' => $id,
                        'remainder' => $numerator
                            % $discountable,
                    ];
                }
                unset($row);

                usort(
                    $remainders,
                    fn ($a, $b) =>
                        ($b['remainder'] <=> $a['remainder'])
                        ?: ($a['id'] <=> $b['id'])
                );

                $rest = $difference - $allocated;
                for ($i = 0; $i < $rest; $i++) {
                    $rows[
                        $remainders[$i]['id']
                    ]['discount_cents']++;
                }
            }
        }

        $selectedTotal = 0;
        $allSelected = true;

        foreach ($rows as $id => &$row) {
            $sold = $row['sold'];
            $raw = (string) ($quantities['line_' . $id] ?? '0');

            if (! $refundAll && ! preg_match(
                '/\A\d+(?:\.\d{1,2})?\z/D',
                $raw
            )) {
                throw new \RuntimeException(
                    'Cantidad no valida.'
                );
            }

            $qty = $refundAll ? $sold : (float) $raw;

            if ($qty < 0 || $qty > $sold) {
                throw new \RuntimeException(
                    'Cantidad fuera de rango.'
                );
            }

            if (abs($qty - $sold) > 0.000001) {
                $allSelected = false;
            }

            $row['quantity'] = $qty;
            $row['net_cents'] =
                $row['gross_cents'] - $row['discount_cents'];

            $row['refund_cents'] = (int) round(
                $row['net_cents'] * ($qty / $sold)
            );

            // BEXIA_V5836_REFUND_NET_ZERO_P10I36
            // Una linea seleccionada no puede registrar
            // devolucion financiera por cero centavos.
            if ($qty > 0 && $row['refund_cents'] <= 0) {
                throw new \RuntimeException(
                    'La linea seleccionada tiene importe neto cero.'
                );
            }

            $selectedTotal += $row['refund_cents'];
        }
        unset($row);

        if ($refundAll || $allSelected) {
            $selectedTotal = $net;
        }

        if ($selectedTotal <= 0 || $selectedTotal > $net) {
            throw new \RuntimeException(
                'Devolucion fuera del total neto.'
            );
        }

        if (array_sum(array_column($rows, 'net_cents')) !== $net) {
            throw new \RuntimeException(
                'La distribucion neta no concilia.'
            );
        }

        return [
            'gross_cents' => $gross,
            'discount_cents' => $difference,
            'net_cents' => $net,
            'refund_cents' => $selectedTotal,
            'lines' => array_values($rows),
        ];
    }
}
