<?php

namespace App\Support\Expenses;

use App\Models\ExpenseCategory;
use App\Models\ExpenseReport;
use RuntimeException;

class ExpenseReportSubmissionValidator
{
    public static function validate(ExpenseReport $report): array
    {
        $report->load([
            'pettyCashFund',
            'lines.category',
            'lines.spentByEmployee',
            'lines.attachments',
        ]);

        $errors = [];

        if ((string) $report->status !== 'draft') {
            $errors[] = 'La comprobación ya no está en borrador.';
        }

        if ($report->lines->isEmpty()) {
            $errors[] = 'Debe capturar por lo menos un gasto.';
        }

        if ((float) $report->total_amount <= 0) {
            $errors[] = 'El total de la comprobación debe ser mayor a cero.';
        }

        $linesTotal = round(
            (float) $report->lines->sum(
                fn ($line) => (float) $line->total_amount
            ),
            2
        );

        $reportTotal = round(
            (float) $report->total_amount,
            2
        );

        if (abs($linesTotal - $reportTotal) > 0.02) {
            $errors[] =
                'El total del reporte no coincide con la suma de sus gastos.';
        }

        foreach ($report->lines as $index => $line) {
            $number = $index + 1;

            if (! $line->expense_category_id) {
                $errors[] =
                    "Gasto {$number}: debe seleccionar una categoría.";
            } elseif (! $line->category) {
                $errors[] =
                    "Gasto {$number}: la categoría seleccionada no existe.";
            } elseif (
                (int) $line->category->company_id
                !== (int) $report->company_id
            ) {
                $errors[] =
                    "Gasto {$number}: la categoría pertenece a otra empresa.";
            } elseif (! (bool) $line->category->is_active) {
                $errors[] =
                    "Gasto {$number}: la categoría seleccionada está inactiva.";
            }

            if (! $line->spent_by_employee_id) {
                $errors[] =
                    "Gasto {$number}: debe indicar quién realizó el gasto.";
            } elseif (! $line->spentByEmployee) {
                $errors[] =
                    "Gasto {$number}: el empleado seleccionado no existe.";
            } elseif (
                (int) $line->spentByEmployee->company_id
                !== (int) $report->company_id
            ) {
                $errors[] =
                    "Gasto {$number}: el empleado pertenece a otra empresa.";
            }

            if (blank($line->payment_method)) {
                $errors[] =
                    "Gasto {$number}: debe seleccionar la forma de pago.";
            }

            if ((float) $line->total_amount <= 0) {
                $errors[] =
                    "Gasto {$number}: el total debe ser mayor a cero.";
            }

            static::validateReceipt(
                $line,
                $number,
                $errors
            );
        }

        static::validateUniqueCfdiUuids(
            $report,
            $errors
        );

        static::validatePettyCash(
            $report,
            $errors
        );

        return array_values(
            array_unique($errors)
        );
    }

    public static function assertCanSubmit(
        ExpenseReport $report
    ): void {
        $errors = static::validate($report);

        if ($errors === []) {
            return;
        }

        throw new RuntimeException(
            "No se puede enviar la comprobación:\n- "
            . implode("\n- ", $errors)
        );
    }

    protected static function validateUniqueCfdiUuids(
        ExpenseReport $report,
        array &$errors
    ): void {
        foreach ($report->lines as $line) {
            if (blank($line->cfdi_uuid)) {
                continue;
            }

            $existing =
                \App\Models\ExpenseReportLine::query()
                    ->where(
                        'cfdi_uuid',
                        $line->cfdi_uuid
                    )
                    ->whereKeyNot($line->id)
                    ->with('report:id,number')
                    ->first();

            if (! $existing) {
                continue;
            }

            $number =
                $existing->report?->number
                ?: '#'
                    . $existing->expense_report_id;

            $errors[] =
                'El UUID CFDI '
                . $line->cfdi_uuid
                . ' ya fue utilizado en la comprobación '
                . $number
                . '.';
        }
    }

    protected static function validateReceipt(
        $line,
        int $number,
        array &$errors
    ): void {
        /** @var ExpenseCategory|null $category */
        $category = $line->category;

        if (! $category) {
            return;
        }

        if (! (bool) $category->requires_receipt) {
            return;
        }

        if ((bool) $line->has_receipt) {
            $hasAttachment =
                $line->attachments->isNotEmpty()
                || filled($line->sat_cfdi_document_id);

            if (! $hasAttachment) {
                $errors[] =
                    "Gasto {$number}: está marcado con comprobante, "
                    . "pero no tiene XML o PDF adjunto.";
            }

            return;
        }

        if (! (bool) $category->allows_without_receipt) {
            $errors[] =
                "Gasto {$number}: la categoría requiere comprobante.";
            return;
        }

        if (blank($line->receipt_exception_reason)) {
            $errors[] =
                "Gasto {$number}: indique el motivo por el que no tiene comprobante.";
        }
    }

    protected static function validatePettyCash(
        ExpenseReport $report,
        array &$errors
    ): void {
        if (
            (string) $report->type
            !== ExpenseReport::TYPE_PETTY_CASH
        ) {
            return;
        }

        $fund = $report->pettyCashFund;

        if (! $fund) {
            $errors[] =
                'Debe seleccionar una caja chica.';
            return;
        }

        if (
            (int) $fund->company_id
            !== (int) $report->company_id
        ) {
            $errors[] =
                'La caja chica pertenece a otra empresa.';
        }

        if (
            ! (bool) $fund->is_active
            || (string) $fund->status !== 'active'
        ) {
            $errors[] =
                'La caja chica no está activa.';
        }

        if (
            strtoupper((string) $fund->currency_code)
            !== strtoupper((string) $report->currency_code)
        ) {
            $errors[] =
                'La moneda de la comprobación no coincide con la caja chica.';
        }

        $balance = round(
            (float) $fund->operational_balance,
            2
        );

        $total = round(
            (float) $report->total_amount,
            2
        );

        if ($total > $balance + 0.001) {
            $errors[] =
                'El total de la comprobación ($'
                . number_format($total, 2)
                . ') excede el saldo disponible de la caja chica ($'
                . number_format($balance, 2)
                . ').';
        }
    }
}
