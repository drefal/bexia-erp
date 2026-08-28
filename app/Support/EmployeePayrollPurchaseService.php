<?php

namespace App\Support;

use App\Models\EmployeePayrollDeduction;
use App\Models\EmployeePayrollPurchase;
use App\Models\EmployeePayrollPurchaseInstallment;
use App\Models\PayrollConcept;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EmployeePayrollPurchaseService
{
    public static function suggestedGrossPrice(Product $product): float
    {
        $base = max(0, (float) ($product->sale_price ?? 0));
        $taxRate = max(0, (float) ($product->sale_tax_rate ?? 0));

        return round($base * (1 + ($taxRate / 100)), 2);
    }

    public static function confirm(
        EmployeePayrollPurchase $purchase,
        ?int $userId = null
    ): EmployeePayrollPurchase {
        return DB::transaction(function () use ($purchase, $userId): EmployeePayrollPurchase {
            $purchase = EmployeePayrollPurchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $purchase->status !== 'draft') {
                throw new \RuntimeException('Solo se puede confirmar una compra en borrador.');
            }

            $purchase->load(['lines.product', 'employee']);

            if ($purchase->lines->isEmpty()) {
                throw new \RuntimeException('La compra debe contener al menos un producto.');
            }

            $installmentsCount = (int) $purchase->installments_count;

            if ($installmentsCount < 1 || $installmentsCount > 104) {
                throw new \RuntimeException('El número de pagos debe estar entre 1 y 104.');
            }

            if (! in_array((string) $purchase->frequency, ['weekly', 'biweekly', 'monthly'], true)) {
                throw new \RuntimeException('Periodicidad inválida.');
            }

            if (! $purchase->first_deduction_date) {
                throw new \RuntimeException('Falta la fecha del primer descuento.');
            }

            if (! $purchase->employee) {
                throw new \RuntimeException('El empleado ya no existe.');
            }

            // BEXIA_V5833G_VALIDATE_PAYROLL_PURCHASE_PRODUCT
            // Defensa de integridad multiempresa antes de generar el descuento.
            if ((int) $purchase->employee->company_id !== (int) $purchase->company_id) {
                throw new \RuntimeException(
                    'El empleado no pertenece a la empresa de esta compra.'
                );
            }

            $subtotal = 0.0;
            $taxTotal = 0.0;
            $total = 0.0;

            foreach ($purchase->lines as $line) {
                $quantity = round(max(0, (float) $line->quantity), 4);

                if ($quantity <= 0) {
                    throw new \RuntimeException('Todas las cantidades deben ser mayores a cero.');
                }

                $product = $line->product;

                // BEXIA_V5833G_VALIDATE_PAYROLL_PURCHASE_PRODUCT
                // El selector ya filtra estos casos; esta validacion evita
                // confirmar borradores antiguos o solicitudes manipuladas.
                if (! $product) {
                    throw new \RuntimeException(
                        'Uno de los productos ya no existe. Revisa el borrador antes de confirmar.'
                    );
                }

                if ((int) $product->company_id !== (int) $purchase->company_id) {
                    throw new \RuntimeException(
                        'Uno de los productos pertenece a otra empresa.'
                    );
                }

                $productSku = strtoupper(
                    trim((string) ($product->sku ?? ''))
                );

                if (str_starts_with($productSku, 'ODOO-HIST-')) {
                    throw new \RuntimeException(
                        'No se puede confirmar una compra con productos auxiliares ODOO-HIST.'
                    );
                }

                if (! (bool) ($product->is_active ?? false)) {
                    throw new \RuntimeException(
                        'Uno de los productos ya no esta activo.'
                    );
                }

                if (! (bool) ($product->can_be_sold ?? false)) {
                    throw new \RuntimeException(
                        'Uno de los productos ya no esta habilitado para venta.'
                    );
                }

                if ((float) ($product->sale_price ?? 0) <= 0) {
                    throw new \RuntimeException(
                        'Uno de los productos ya no tiene precio de venta valido.'
                    );
                }

                $taxRate = max(
                    0,
                    (float) ($line->tax_rate ?? $product?->sale_tax_rate ?? 0)
                );

                $grossUnit = max(0, (float) $line->unit_price_with_tax);

                if ($grossUnit <= 0 && $product) {
                    $grossUnit = static::suggestedGrossPrice($product);
                }

                if ($grossUnit <= 0) {
                    throw new \RuntimeException(
                        'Todos los productos deben tener un precio con IVA mayor a cero.'
                    );
                }

                $factor = 1 + ($taxRate / 100);
                $baseUnit = $factor > 0
                    ? round($grossUnit / $factor, 4)
                    : round($grossUnit, 4);

                $lineSubtotal = round($baseUnit * $quantity, 2);
                $lineTotal = round($grossUnit * $quantity, 2);
                $lineTax = round($lineTotal - $lineSubtotal, 2);

                $line->forceFill([
                    'company_id' => $purchase->company_id,
                    'product_sku' => $line->product_sku ?: $product?->sku,
                    'product_reference' => $line->product_reference ?: $product?->internal_reference,
                    'product_name' => $line->product_name ?: ($product?->name ?: 'Producto'),
                    'variant_name' => $line->variant_name ?: $product?->variant_name,
                    'quantity' => $quantity,
                    'unit_price_without_tax' => $baseUnit,
                    'tax_rate' => $taxRate,
                    'unit_price_with_tax' => round($grossUnit, 4),
                    'line_subtotal' => $lineSubtotal,
                    'line_tax' => $lineTax,
                    'line_total' => $lineTotal,
                ])->save();

                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
                $total += $lineTotal;
            }

            $subtotal = round($subtotal, 2);
            $taxTotal = round($taxTotal, 2);
            $total = round($total, 2);

            if ($total <= 0) {
                throw new \RuntimeException('El total debe ser mayor a cero.');
            }

            $concept = PayrollConcept::query()->firstOrCreate(
                [
                    'company_id' => $purchase->company_id,
                    'code' => 'COMPRA_EMPLEADO',
                ],
                [
                    'name' => 'Compra vía nómina',
                    'type' => 'deduction',
                    'category' => 'manual',
                    'source' => 'manual',
                    'unit' => 'amount',
                    'is_active' => true,
                    'sort_order' => 240,
                    'notes' => 'Cuotas programadas de compras de productos de empleados.',
                ],
            );

            $schedule = static::schedule(
                total: $total,
                installmentsCount: $installmentsCount,
                firstDate: CarbonImmutable::parse($purchase->first_deduction_date),
                frequency: (string) $purchase->frequency,
            );

            $firstAmount = (float) $schedule[0]['amount'];
            $lastRow = $schedule[count($schedule) - 1];

            $deduction = EmployeePayrollDeduction::create([
                'company_id' => $purchase->company_id,
                'employee_id' => $purchase->employee_id,
                'employee_payroll_purchase_id' => $purchase->id,
                'payroll_concept_id' => $concept->id,
                'type' => 'product_purchase',
                'code' => 'COMPRA_EMPLEADO',
                'name' => 'Compra vía nómina ' . $purchase->number,
                'original_amount' => $total,
                'outstanding_amount' => $total,
                'period_amount' => $firstAmount,
                'start_date' => $purchase->first_deduction_date,
                'end_date' => $lastRow['date'],
                'max_periods' => $installmentsCount,
                'applied_periods' => 0,
                'status' => 'active',
                'notes' => 'Generado desde ' . $purchase->number . '. Calendario controlado por cuotas.',
                'created_by_user_id' => $userId ?: auth()->id(),
                'updated_by_user_id' => $userId ?: auth()->id(),
            ]);

            foreach ($schedule as $row) {
                EmployeePayrollPurchaseInstallment::create([
                    'company_id' => $purchase->company_id,
                    'employee_payroll_purchase_id' => $purchase->id,
                    'employee_id' => $purchase->employee_id,
                    'employee_payroll_deduction_id' => $deduction->id,
                    'installment_number' => $row['number'],
                    'due_date' => $row['date'],
                    'scheduled_amount' => $row['amount'],
                    'applied_amount' => 0,
                    'status' => 'pending',
                ]);
            }

            $purchase->forceFill([
                'branch_id' => $purchase->branch_id ?: $purchase->employee->branch_id,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total_amount' => $total,
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $userId ?: auth()->id(),
                'updated_by_user_id' => $userId ?: auth()->id(),
            ])->save();

            return $purchase->fresh(['employee', 'lines', 'installments', 'deduction']);
        });
    }

    public static function cancel(
        EmployeePayrollPurchase $purchase,
        ?int $userId = null
    ): EmployeePayrollPurchase {
        return DB::transaction(function () use ($purchase, $userId): EmployeePayrollPurchase {
            $purchase = EmployeePayrollPurchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $purchase->status !== 'confirmed') {
                throw new \RuntimeException('Solo se puede cancelar una compra confirmada.');
            }

            if ($purchase->installments()->where('status', 'applied')->exists()) {
                throw new \RuntimeException(
                    'La compra ya tiene cuotas aplicadas. No se puede cancelar automáticamente.'
                );
            }

            $deduction = $purchase->deduction()->lockForUpdate()->first();

            if ($deduction && $deduction->applications()->exists()) {
                throw new \RuntimeException(
                    'El descuento ya tiene aplicaciones de nómina.'
                );
            }

            $purchase->installments()->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

            if ($deduction) {
                $deduction->forceFill([
                    'status' => 'cancelled',
                    'updated_by_user_id' => $userId ?: auth()->id(),
                ])->save();
            }

            $purchase->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId ?: auth()->id(),
                'updated_by_user_id' => $userId ?: auth()->id(),
            ])->save();

            return $purchase->fresh(['installments', 'deduction']);
        });
    }

    public static function schedule(
        float $total,
        int $installmentsCount,
        CarbonImmutable $firstDate,
        string $frequency
    ): array {
        $totalCents = (int) round($total * 100);

        if ($totalCents <= 0 || $installmentsCount < 1) {
            throw new \InvalidArgumentException('Total/pagos inválidos.');
        }

        if (! in_array($frequency, ['weekly', 'biweekly', 'monthly'], true)) {
            throw new \InvalidArgumentException('Periodicidad inválida.');
        }

        $regularCents = intdiv($totalCents, $installmentsCount);
        $remainder = $totalCents - ($regularCents * $installmentsCount);
        $rows = [];

        for ($i = 1; $i <= $installmentsCount; $i++) {
            $amountCents = $regularCents + ($i === $installmentsCount ? $remainder : 0);

            $dueDate = match ($frequency) {
                'weekly' => $firstDate->addDays(($i - 1) * 7),
                'biweekly' => $firstDate->addDays(($i - 1) * 15),
                'monthly' => $firstDate->addMonthsNoOverflow($i - 1),
            };

            $rows[] = [
                'number' => $i,
                'date' => $dueDate->toDateString(),
                'amount' => round($amountCents / 100, 2),
            ];
        }

        return $rows;
    }

    public static function weeklyReportData(
        int $companyId,
        string|\DateTimeInterface $weekDate
    ): array {
        $date = CarbonImmutable::parse($weekDate);
        $from = $date->startOfWeek();
        $to = $from->addDays(6);

        $installments = EmployeePayrollPurchaseInstallment::query()
            ->with(['employee', 'purchase.lines', 'purchase.deduction'])
            ->where('company_id', $companyId)
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '<>', 'cancelled')
            ->orderBy('due_date')
            ->orderBy('employee_id')
            ->orderBy('id')
            ->get();

        $rows = $installments->map(function (EmployeePayrollPurchaseInstallment $installment): array {
            $purchase = $installment->purchase;

            $products = $purchase?->lines
                ?->map(function ($line): string {
                    $name = trim((string) $line->product_name);
                    $variant = trim((string) $line->variant_name);

                    if ($variant !== '') {
                        $name .= ' / ' . $variant;
                    }

                    $qty = rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.');

                    return $qty . ' × ' . $name;
                })
                ->implode('; ') ?: '';

            return [
                'employee' => $installment->employee?->name ?: 'Empleado',
                'purchase_number' => $purchase?->number,
                'products' => $products,
                'installment' => $installment->installment_number
                    . '/'
                    . ($purchase?->installments_count ?? '?'),
                'due_date' => $installment->due_date?->toDateString(),
                'scheduled_amount' => (float) $installment->scheduled_amount,
                'applied_amount' => (float) $installment->applied_amount,
                'status' => (string) $installment->status,
                'outstanding_amount' => (float) ($purchase?->deduction?->outstanding_amount ?? 0),
            ];
        })->values()->all();

        return [
            'company_id' => $companyId,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'scheduled_total' => round(array_sum(array_column($rows, 'scheduled_amount')), 2),
            'applied_total' => round(array_sum(array_column($rows, 'applied_amount')), 2),
            'pending_total' => round(array_sum(array_map(
                fn (array $row): float => $row['status'] === 'pending'
                    ? (float) $row['scheduled_amount']
                    : 0.0,
                $rows
            )), 2),
        ];
    }
}
