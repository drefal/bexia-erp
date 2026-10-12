<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PayrollRunReadinessService
{
    public static function validate(PayrollRun $run): array
    {
        $errors = [];
        $warnings = [];

        if (blank($run->payroll_group_id)) {
            $errors[] = 'La prenómina no tiene un grupo de nómina seleccionado.';

            return [
                'ready' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'summary' => [
                    'employees_count' => 0,
                    'errors_count' => count($errors),
                    'warnings_count' => 0,
                ],
            ];
        }

        $periodStart = CarbonImmutable::parse($run->period_start)->startOfDay();
        $periodEnd = CarbonImmutable::parse($run->period_end)->startOfDay();

        $employees = static::employeesForRun($run);

        foreach ($employees as $employee) {
            $label = trim(
                ($employee->name ?: 'Empleado')
                . ' [ID ' . $employee->id . ']'
            );

            if (blank($employee->hire_date)) {
                $errors[] = "{$label}: falta Fecha de ingreso a la empresa.";
            } elseif (
                CarbonImmutable::parse($employee->hire_date)
                    ->startOfDay()
                    ->greaterThan($periodEnd)
            ) {
                $errors[] = "{$label}: la fecha de ingreso es posterior al periodo de nómina.";
            }

            $contract = static::contractForPeriod(
                $employee,
                $periodStart,
                $periodEnd
            );

            if (! $contract) {
                $errors[] = "{$label}: no tiene contrato aplicable al periodo.";
                continue;
            }

            $folio = $contract->contract_number
                ?: ('Contrato #' . $contract->id);

            if (in_array(
                (string) $contract->status,
                ['draft', 'cancelled'],
                true
            )) {
                $errors[] =
                    "{$label}: {$folio} está en estado "
                    . ($contract->status ?: 'sin estado')
                    . " y no puede usarse para prenómina.";
            }

            $scheme = (string) ($contract->payment_scheme ?: 'fixed');

            if (! in_array($scheme, ['fixed'], true)) {
                $errors[] =
                    "{$label}: {$folio} usa esquema "
                    . static::schemeLabel($scheme)
                    . "; el cálculo de destajo/mixto todavía no está implementado.";
            }

            if (blank($contract->payroll_periodicity_id)) {
                $errors[] =
                    "{$label}: {$folio} no tiene periodicidad de nómina.";
            }

            if (blank($contract->salary_type)) {
                $errors[] =
                    "{$label}: {$folio} no tiene modalidad del sueldo.";
            }

            if ((float) ($contract->base_salary ?? 0) <= 0) {
                $errors[] =
                    "{$label}: {$folio} no tiene sueldo base válido.";
            }

            if ((float) ($contract->hours_per_week ?? 0) <= 0) {
                $errors[] =
                    "{$label}: {$folio} no tiene horas por semana válidas.";
            }

            if (blank($contract->hr_work_schedule_id)) {
                $warnings[] =
                    "{$label}: {$folio} no tiene horario laboral asignado.";
            }
        }

        if ($employees->isEmpty()) {
            $warnings[] = 'No hay empleados que correspondan al periodo de esta prenómina.';
        }

        return [
            'ready' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'employees_count' => $employees->count(),
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
            ],
        ];
    }

    public static function assertReady(PayrollRun $run): void
    {
        $result = static::validate($run);

        if ($result['ready']) {
            return;
        }

        $errors = $result['errors'];

        $visible = array_slice($errors, 0, 12);

        $message =
            "No se puede calcular la prenómina porque hay datos incompletos:\n- "
            . implode("\n- ", $visible);

        $remaining = count($errors) - count($visible);

        if ($remaining > 0) {
            $message .=
                "\n- ... y {$remaining} observación(es) adicional(es).";
        }

        throw new \RuntimeException($message);
    }

    public static function employeesForRun(PayrollRun $run): Collection
    {
        return Employee::query()
            ->where('company_id', $run->company_id)
            ->where('payroll_group_id', $run->payroll_group_id)
            ->where(function ($query) use ($run): void {
                $query->where('active', true)
                    ->orWhere(function ($query) use ($run): void {
                        $query->whereNotNull('termination_date')
                            ->whereDate(
                                'termination_date',
                                '>=',
                                $run->period_start
                            );
                    });
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('hire_date')
                    ->orWhereDate(
                        'hire_date',
                        '<=',
                        $run->period_end
                    );
            })
            ->orderBy('name')
            ->get();
    }

    public static function contractForPeriod(
        Employee $employee,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd
    ): ?EmployeeContract {
        return EmployeeContract::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($periodEnd): void {
                $query->whereNull('start_date')
                    ->orWhereDate(
                        'start_date',
                        '<=',
                        $periodEnd->toDateString()
                    );
            })
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('end_date')
                    ->orWhereDate(
                        'end_date',
                        '>=',
                        $periodStart->toDateString()
                    );
            })
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    protected static function schemeLabel(string $scheme): string
    {
        return match ($scheme) {
            'fixed' => 'Sueldo fijo',
            'piecework' => 'Destajo',
            'mixed' => 'Mixto',
            default => $scheme !== '' ? $scheme : 'sin definir',
        };
    }
}
