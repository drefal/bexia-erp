<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Distribucion fiscal pura para el nuevo flujo multimétodo.
 * No consulta tablas ni registra movimientos.
 * Todos los importes retornados usan 4 decimales.
 */
final class PosRefundFiscalAllocator
{
    private static function units4($value): int
    {
        if (! is_numeric($value)) {
            throw new \RuntimeException(
                'Importe fiscal no numerico.'
            );
        }

        return (int) round((float) $value * 10000);
    }

    private static function money4(int $units): string
    {
        return number_format($units / 10000, 4, '.', '');
    }

    /**
     * @param array $preview Resultado de PosRefundNetCalculator
     * @param array $originalLines Lineas originales completas del ticket
     */
    public static function allocate(
        array $preview,
        array $originalLines
    ): array {
        $original = [];

        foreach ($originalLines as $line) {
            $id = (int) ($line->id ?? 0);

            if ($id <= 0 || isset($original[$id])) {
                throw new \RuntimeException(
                    'Identificador de linea fiscal invalido.'
                );
            }

            foreach (['subtotal', 'tax_total', 'total'] as $field) {
                if (! isset($line->$field)) {
                    throw new \RuntimeException(
                        'Falta importe fiscal original: ' . $field
                    );
                }
            }

            $base = self::units4($line->subtotal);
            $tax = self::units4($line->tax_total);
            $total = self::units4($line->total);

            if ($base < 0 || $tax < 0 || $total < 0
                || abs(($base + $tax) - $total) > 1) {
                throw new \RuntimeException(
                    'Linea fiscal original no conciliada.'
                );
            }

            $original[$id] = [
                'subtotal' => $base,
                'tax' => $tax,
                'total' => $total,
            ];
        }

        $rows = $preview['lines'] ?? null;
        $authorizedCents = $preview['refund_cents'] ?? null;

        if (! is_array($rows)
            || ! is_int($authorizedCents)
            || $authorizedCents <= 0) {
            throw new \RuntimeException(
                'Vista previa neta invalida.'
            );
        }

        $result = [];
        $sumBase = 0;
        $sumTax = 0;
        $sumTotal = 0;
        $seen = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);
            $cents = $row['refund_cents'] ?? null;

            if ($id <= 0 || isset($seen[$id])
                || ! isset($original[$id])
                || ! is_int($cents)
                || $cents < 0
                || $quantity < 0) {
                throw new \RuntimeException(
                    'Linea de distribucion fiscal invalida.'
                );
            }

            $seen[$id] = true;

            if ($quantity <= 0) {
                if ($cents !== 0) {
                    throw new \RuntimeException(
                        'Importe positivo sin cantidad.'
                    );
                }
                continue;
            }

            $source = $original[$id];
            $netUnits = $cents * 100;

            // La proporcion proviene del IVA y subtotal
            // realmente guardados, no de reinterpretar tax_rate.
            if ($source['total'] <= 0 && $netUnits > 0) {
                throw new \RuntimeException(
                    'Linea sin base fiscal verificable.'
                );
            }

            $taxUnits = $source['total'] > 0
                ? (int) round(
                    $netUnits * $source['tax']
                    / $source['total']
                )
                : 0;

            $baseUnits = $netUnits - $taxUnits;

            if ($baseUnits < 0 || $taxUnits < 0) {
                throw new \RuntimeException(
                    'Distribucion de impuesto invalida.'
                );
            }

            $result[] = [
                'pos_order_line_id' => $id,
                'quantity' => $quantity,
                'subtotal' => self::money4($baseUnits),
                'tax_total' => self::money4($taxUnits),
                'total' => self::money4($netUnits),
                'refund_cents' => $cents,
            ];

            $sumBase += $baseUnits;
            $sumTax += $taxUnits;
            $sumTotal += $netUnits;
        }

        if ($result === []
            || $sumTotal !== $authorizedCents * 100
            || $sumBase + $sumTax !== $sumTotal) {
            throw new \RuntimeException(
                'La distribucion fiscal no concilia.'
            );
        }

        return [
            'subtotal' => self::money4($sumBase),
            'tax_total' => self::money4($sumTax),
            'total' => self::money4($sumTotal),
            'total_cents' => $authorizedCents,
            'lines' => $result,
        ];
    }
}
