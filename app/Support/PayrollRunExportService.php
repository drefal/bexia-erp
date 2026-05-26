<?php

namespace App\Support;

use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunExportService
{
    public static function data(PayrollRun $run): array
    {
        $run->loadMissing([
            'company',
            'calculatedBy',
            'lines.employee.hrDepartment',
            'lines.employee.hrJobPosition',
            'lines.contract',
            'lines.concepts',
        ]);

        return [
            'run' => $run,
            'company' => $run->company,
            'lines' => $run->lines->sortBy(fn ($line) => $line->employee?->name ?? ''),
            'summary' => $run->summary ?: [],
            'generatedAt' => now(),
            'statusOptions' => PayrollRun::statusOptions(),
            'periodTypeOptions' => PayrollRun::periodTypeOptions(),
        ];
    }

    public static function writeExcel(string $path, PayrollRun $run): void
    {
        $data = static::data($run);
        $run = $data['run'];
        $lines = $data['lines'];

        $writer = new Writer();
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(['Bexia ERP - Pre-nómina']));
        $writer->addRow(Row::fromValues(['Empresa', $data['company']?->name ?? '']));
        $writer->addRow(Row::fromValues([
            'Pre-nómina',
            $run->name,
            'Estado',
            static::statusLabel($run->status),
            'Periodicidad',
            static::periodTypeLabel($run->period_type),
        ]));
        $writer->addRow(Row::fromValues([
            'Inicio',
            static::dateOnly($run->period_start),
            'Fin',
            static::dateOnly($run->period_end),
            'Fecha de pago',
            static::dateOnly($run->payment_date),
        ]));
        $writer->addRow(Row::fromValues(['Generado', $data['generatedAt']->format('Y-m-d H:i:s')]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Resumen']));
        $writer->addRow(Row::fromValues(['Empleados', (int) $run->employees_count]));
        $writer->addRow(Row::fromValues(['Sueldo base', (float) $run->base_total]));
        $writer->addRow(Row::fromValues(['Horas extra', (float) $run->overtime_total]));
        $writer->addRow(Row::fromValues(['Percepciones', (float) $run->perceptions_total]));
        $writer->addRow(Row::fromValues(['Bruto', (float) $run->gross_total]));
        $writer->addRow(Row::fromValues(['Deducciones', (float) $run->deductions_total]));
        $writer->addRow(Row::fromValues(['Neto', (float) $run->net_total]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues([
            'Empleado',
            'Número',
            'Departamento',
            'Puesto',
            'Contrato',
            'Sueldo base',
            'Tipo sueldo',
            'Salario diario',
            'Tarifa hora',
            'Días periodo',
            'Días pagables',
            'Registros asistencia',
            'Horas trabajadas',
            'Min. retardo',
            'Min. salida temprana',
            'Min. extra',
            'Horas extra',
            'Faltas',
            'Descansos trabajados',
            'Incidencias aprobadas',
            'Base',
            'Extra',
            'Percepciones',
            'Deducciones incidencias',
            'Deducciones total',
            'Bruto',
            'Neto',
            'Notas',
        ]));

        foreach ($lines as $line) {
            $employee = $line->employee;

            $writer->addRow(Row::fromValues([
                $employee?->name,
                $employee?->employee_number,
                $employee?->hrDepartment?->name,
                $employee?->hrJobPosition?->name,
                $line->contract?->contract_number,
                (float) $line->base_salary,
                $line->salary_type,
                (float) $line->daily_salary,
                (float) $line->hourly_rate,
                (float) $line->period_days,
                (float) $line->payable_days,
                (int) $line->attendance_records,
                (float) $line->worked_hours,
                (int) $line->late_minutes,
                (int) $line->early_leave_minutes,
                (int) $line->overtime_minutes,
                (float) $line->overtime_hours,
                (float) $line->absence_days,
                (float) $line->rest_day_worked_days,
                (int) $line->approved_incidents_count,
                (float) $line->base_amount,
                (float) $line->overtime_amount,
                (float) $line->incident_perceptions,
                (float) $line->incident_deductions,
                (float) $line->deductions_amount,
                (float) $line->gross_amount,
                (float) $line->net_amount,
                $line->notes,
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Conceptos por empleado']));
        $writer->addRow(Row::fromValues([
            'Empleado',
            'Código',
            'Concepto',
            'Tipo',
            'Categoría',
            'Origen',
            'Unidad',
            'Cantidad',
            'Tarifa',
            'Importe',
        ]));

        foreach ($lines as $line) {
            foreach ($line->concepts as $concept) {
                $writer->addRow(Row::fromValues([
                    $line->employee?->name,
                    $concept->code,
                    $concept->name,
                    $concept->type,
                    $concept->category,
                    $concept->source,
                    $concept->unit,
                    (float) $concept->quantity,
                    (float) $concept->rate,
                    (float) $concept->amount,
                ]));
            }
        }

        $writer->close();
    }


    public static function receiptData(PayrollRunLine $line): array
    {
        $line->loadMissing([
            'company',
            'payrollRun.company',
            'payrollRun.calculatedBy',
            'employee.hrDepartment',
            'employee.hrJobPosition',
            'contract',
            'concepts',
        ]);

        $run = $line->payrollRun;

        return [
            'line' => $line,
            'run' => $run,
            'company' => $run?->company ?: $line->company,
            'employee' => $line->employee,
            'contract' => $line->contract,
            'generatedAt' => now(),
            'statusOptions' => PayrollRun::statusOptions(),
            'periodTypeOptions' => PayrollRun::periodTypeOptions(),
            'legend' => 'Recibo interno sin validez CFDI. Este documento es una vista operativa de pre-nómina y no sustituye el CFDI de nómina timbrado.',
        ];
    }

    public static function exportReceiptPdf(PayrollRunLine $line): StreamedResponse
    {
        if (! app()->bound('dompdf.wrapper')) {
            throw new \RuntimeException('No hay motor PDF instalado (barryvdh/laravel-dompdf).');
        }

        $data = static::receiptData($line);

        $pdf = app('dompdf.wrapper')
            ->loadView('reports.hr.payroll-run-line-receipt-pdf', $data)
            ->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, static::receiptFilename($line), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public static function receiptFilename(PayrollRunLine $line): string
    {
        $line->loadMissing(['payrollRun', 'employee']);

        $run = $line->payrollRun;
        $from = $run?->period_start?->format('Ymd') ?: 'sin_inicio';
        $to = $run?->period_end?->format('Ymd') ?: 'sin_fin';
        $employeeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($line->employee?->name ?: 'empleado'));

        return "recibo_interno_nomina_{$line->id}_{$from}_{$to}_{$employeeName}.pdf";
    }

    public static function statusLabel(?string $status): string
    {
        return PayrollRun::statusOptions()[$status] ?? ($status ?: '-');
    }

    public static function periodTypeLabel(?string $type): string
    {
        return PayrollRun::periodTypeOptions()[$type] ?? ($type ?: '-');
    }

    public static function dateOnly(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public static function money(mixed $value): string
    {
        return '$' . number_format((float) ($value ?? 0), 2);
    }
}
