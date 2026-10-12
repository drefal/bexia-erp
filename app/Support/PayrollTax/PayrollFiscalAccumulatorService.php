<?php

namespace App\Support\PayrollTax;

use App\Models\PayrollFiscalEmployeeMonth;
use App\Models\PayrollFiscalRunResult;
use App\Models\PayrollRun;
use App\Models\PayrollRunVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollFiscalAccumulatorService
{
    /**
     * Resultados fiscales EFECTIVOS.
     *
     * Reglas:
     *
     * - fiscal_preview NUNCA es efectivo;
     * - sólo fiscal_cfdi puede alimentar acumulados definitivos;
     * - la corrida debe estar approved o closed;
     * - de cada corrida + empleado usamos sólo la versión más reciente;
     * - una corrida cancelada/recalculable/preliminar no participa.
     */
    protected static function effectiveResults(
        int $companyId,
        int $employeeId,
        int $year,
        int $month,
        ?int $excludePayrollRunId = null
    ): Collection {
        $query = PayrollFiscalRunResult::query()
            ->with([
                'payrollRun',
                'payrollRunVersion',
            ])
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->whereNotNull('payroll_run_version_id')
            ->where(
                'calculation_mode_snapshot',
                'fiscal_cfdi'
            );

        if ($excludePayrollRunId) {
            $query->where(
                'payroll_run_id',
                '!=',
                $excludePayrollRunId
            );
        }

        return $query
            ->get()
            ->filter(function ($row): bool {
                $run = $row->payrollRun;
                $version = $row->payrollRunVersion;

                if (! $run || ! $version) {
                    return false;
                }

                if (
                    (string) $version
                        ->calculation_mode_snapshot
                    !== 'fiscal_cfdi'
                ) {
                    return false;
                }

                /*
                 * El resultado sólo se vuelve fiscalmente efectivo
                 * después de aprobar la corrida.
                 */
                if (! in_array(
                    (string) $run->status,
                    [
                        'approved',
                        'closed',
                    ],
                    true
                )) {
                    return false;
                }

                return true;
            })
            ->groupBy(
                fn ($row): string =>
                    (string) $row->payroll_run_id
                    . ':'
                    . (string) $row->employee_id
            )
            ->map(
                fn (Collection $rows) =>
                    $rows
                        ->sortByDesc(
                            fn ($row): int =>
                                (int) (
                                    $row
                                        ->payrollRunVersion
                                        ?->version_number
                                    ?? 0
                                )
                        )
                        ->first()
            )
            ->filter()
            ->values();
    }

    public static function rebuildEmployeeMonth(
        int $companyId,
        int $employeeId,
        int $year,
        int $month
    ): PayrollFiscalEmployeeMonth {
        return DB::transaction(function () use (
            $companyId,
            $employeeId,
            $year,
            $month
        ): PayrollFiscalEmployeeMonth {
            $results = static::effectiveResults(
                $companyId,
                $employeeId,
                $year,
                $month
            )
                ->sortBy('payment_date')
                ->values();

            $ordinaryCount = $results
                ->where('run_type', 'ordinary')
                ->count();

            $extraordinaryCount = $results
                ->where('run_type', 'extraordinary')
                ->count();

            $lastRunId = $results
                ->sortByDesc(
                    fn ($row): string =>
                        (string) $row->payment_date
                        . '-'
                        . str_pad(
                            (string) $row->id,
                            20,
                            '0',
                            STR_PAD_LEFT
                        )
                )
                ->first()
                ?->payroll_run_id;

            return PayrollFiscalEmployeeMonth::query()
                ->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'employee_id' => $employeeId,
                        'fiscal_year' => $year,
                        'fiscal_month' => $month,
                    ],
                    [
                        'taxable_income' => round(
                            (float) $results->sum(
                                'taxable_income'
                            ),
                            6
                        ),

                        'exempt_income' => round(
                            (float) $results->sum(
                                'exempt_income'
                            ),
                            6
                        ),

                        'isr_before_subsidy' => round(
                            (float) $results->sum(
                                'isr_before_subsidy'
                            ),
                            6
                        ),

                        /*
                         * BEXIA_V5836_PERSIST_SUBSIDY_CAUSED
                         */
                        'subsidy_caused' => round(
                            (float) $results->sum(
                                'subsidy_caused'
                            ),
                            6
                        ),

                        'subsidy_applied' => round(
                            (float) $results->sum(
                                'subsidy_applied'
                            ),
                            6
                        ),

                        'isr_withheld' => round(
                            (float) $results->sum(
                                'isr_withheld'
                            ),
                            6
                        ),

                        'ordinary_runs_count' =>
                            $ordinaryCount,

                        'extraordinary_runs_count' =>
                            $extraordinaryCount,

                        'last_payroll_run_id' =>
                            $lastRunId,

                        'calculated_at' => now(),
                    ]
                );
        });
    }

    /**
     * Recalcula todos los acumulados mensuales afectados por
     * una corrida que acaba de volverse efectiva.
     */
    public static function rebuildRunMonths(
        PayrollRun $run
    ): void {
        if (! $run->enablesCfdi()) {
            return;
        }

        if (! in_array(
            (string) $run->status,
            [
                'approved',
                'closed',
            ],
            true
        )) {
            return;
        }

        if (! $run->payment_date) {
            throw new \RuntimeException(
                'La corrida no tiene fecha de pago.'
            );
        }

        $paymentDate = CarbonImmutable::parse(
            $run->payment_date
        );

        $employeeIds = PayrollFiscalRunResult::query()
            ->where('payroll_run_id', $run->id)
            ->where(
                'calculation_mode_snapshot',
                'fiscal_cfdi'
            )
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        foreach ($employeeIds as $employeeId) {
            static::rebuildEmployeeMonth(
                (int) $run->company_id,
                $employeeId,
                (int) $paymentDate->year,
                (int) $paymentDate->month
            );
        }
    }

    /**
     * Valores fiscales definitivos previos del mes.
     *
     * IMPORTANTE:
     * fiscal_preview no entra aquí.
     *
     * Esto permite que una simulación fiscal consulte la historia
     * real aprobada sin convertir otras simulaciones en antecedentes.
     */
    public static function priorMonthValues(
        int $companyId,
        int $employeeId,
        CarbonImmutable|string $paymentDate,
        ?int $excludePayrollRunId = null
    ): array {
        $date = $paymentDate instanceof CarbonImmutable
            ? $paymentDate
            : CarbonImmutable::parse($paymentDate);

        $rows = static::effectiveResults(
            $companyId,
            $employeeId,
            (int) $date->year,
            (int) $date->month,
            $excludePayrollRunId
        );

        return [
            'taxable_income' => round(
                (float) $rows->sum('taxable_income'),
                6
            ),

            'exempt_income' => round(
                (float) $rows->sum('exempt_income'),
                6
            ),

            'isr_before_subsidy' => round(
                (float) $rows->sum(
                    'isr_before_subsidy'
                ),
                6
            ),

            'subsidy_caused' => round(
                (float) $rows->sum('subsidy_caused'),
                6
            ),

            'subsidy_applied' => round(
                (float) $rows->sum('subsidy_applied'),
                6
            ),

            'isr_withheld' => round(
                (float) $rows->sum('isr_withheld'),
                6
            ),
        ];
    }

    public static function storeRunResult(
        PayrollRun $run,
        PayrollRunVersion $version,
        int $employeeId,
        ?int $payrollRunLineId,
        float $taxableIncome,
        float $exemptIncome,
        float $isrBeforeSubsidy,
        float $subsidyCaused,
        float $subsidyApplied,
        float $isrWithheld,
        ?string $isrTariffCode = null,
        ?string $subsidyRuleCode = null,
        array $snapshot = []
    ): PayrollFiscalRunResult {
        if (! $run->calculatesFiscalTaxes()) {
            throw new \RuntimeException(
                'La corrida no está configurada '
                . 'para cálculo fiscal.'
            );
        }

        if ((int) $version->payroll_run_id !== (int) $run->id) {
            throw new \RuntimeException(
                'La versión no pertenece a la corrida.'
            );
        }

        if (! in_array(
            (string) $version->calculation_mode_snapshot,
            [
                'fiscal_preview',
                'fiscal_cfdi',
            ],
            true
        )) {
            throw new \RuntimeException(
                'La versión indicada no es fiscal.'
            );
        }

        if (
            (string) $version->calculation_mode_snapshot
            !== (string) $run->calculationMode()
        ) {
            throw new \RuntimeException(
                'El modo fiscal de la versión no coincide '
                . 'con el snapshot de la corrida.'
            );
        }

        if (! $run->payment_date) {
            throw new \RuntimeException(
                'La corrida no tiene fecha de pago.'
            );
        }

        $date = CarbonImmutable::parse(
            $run->payment_date
        );

        $runType = in_array(
            (string) $run->run_type,
            [
                'ordinary',
                'extraordinary',
            ],
            true
        )
            ? (string) $run->run_type
            : 'ordinary';

        return PayrollFiscalRunResult::query()
            ->updateOrCreate(
                [
                    'payroll_run_version_id' =>
                        $version->id,

                    'employee_id' =>
                        $employeeId,
                ],
                [
                    'company_id' =>
                        $run->company_id,

                    'payroll_run_id' =>
                        $run->id,

                    'payroll_run_line_id' =>
                        $payrollRunLineId,

                    'calculation_mode_snapshot' =>
                        $version
                            ->calculation_mode_snapshot,

                    'run_type' =>
                        $runType,

                    'payment_date' =>
                        $date->toDateString(),

                    'fiscal_year' =>
                        $date->year,

                    'fiscal_month' =>
                        $date->month,

                    'taxable_income' =>
                        round($taxableIncome, 6),

                    'exempt_income' =>
                        round($exemptIncome, 6),

                    'isr_before_subsidy' =>
                        round($isrBeforeSubsidy, 6),

                    'subsidy_caused' =>
                        round($subsidyCaused, 6),

                    'subsidy_applied' =>
                        round($subsidyApplied, 6),

                    'isr_withheld' =>
                        round($isrWithheld, 6),

                    'isr_tariff_code' =>
                        $isrTariffCode,

                    'subsidy_rule_code' =>
                        $subsidyRuleCode,

                    'calculation_snapshot' =>
                        $snapshot,
                ]
            );
    }
}
