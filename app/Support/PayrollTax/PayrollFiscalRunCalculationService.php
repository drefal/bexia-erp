<?php

namespace App\Support\PayrollTax;

use App\Models\Employee;
use App\Models\PayrollConcept;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PayrollRunLineConcept;
use App\Models\PayrollRunVersion;

class PayrollFiscalRunCalculationService
{
    public static function applyToLine(
        PayrollRun $run,
        Employee $employee,
        PayrollRunLine $line
    ): array {
        if (! $run->calculatesFiscalTaxes()) {
            throw new \RuntimeException(
                'La corrida no está configurada '
                . 'para cálculo fiscal.'
            );
        }

        /*
         * PayrollFiscalPreviewService contiene las reglas
         * actuales de:
         *
         * - base gravable;
         * - ISR;
         * - subsidio;
         * - acumulados previos.
         */
        $preview =
            PayrollFiscalPreviewService::previewEmployee(
                $run,
                $employee
            );

        $reconciliation =
            PayrollEmploymentSubsidyReconciliationService
                ::calculate(
                    $run,
                    $preview
                );

        $currentIsrAmount = round(
            (float) $preview[
                'isr_withheld_preview'
            ],
            2
        );

        $isrAdjustment = round(
            (float) $reconciliation[
                'isr_adjustment'
            ],
            2
        );

        $isrAmount = round(
            $currentIsrAmount
            + $isrAdjustment,
            2
        );

        /*
         * No permitimos crear ISR implícitamente.
         * Debe estar configurado explícitamente en catálogo.
         */
        $isrConcept = PayrollConcept::query()
            ->where(
                'company_id',
                $run->company_id
            )
            ->where(
                'code',
                'ISR'
            )
            ->where(
                'type',
                'deduction'
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $isrConcept) {
            throw new \RuntimeException(
                'No existe el concepto fiscal ISR activo '
                . 'para la empresa.'
            );
        }

        if ((string) $isrConcept->sat_key !== '002') {
            throw new \RuntimeException(
                'El concepto ISR debe tener '
                . 'clave SAT 002.'
            );
        }

        /*
         * Defensa contra duplicados en una misma línea.
         */
        PayrollRunLineConcept::query()
            ->where(
                'payroll_run_line_id',
                $line->id
            )
            ->where(
                'code',
                'ISR'
            )
            ->delete();

        PayrollRunLineConcept::create([
            'company_id' =>
                $line->company_id,

            'payroll_run_id' =>
                $run->id,

            'payroll_run_line_id' =>
                $line->id,

            'employee_id' =>
                $employee->id,

            'payroll_concept_id' =>
                $isrConcept->id,

            'code' =>
                $isrConcept->code,

            'name' =>
                $isrConcept->name,

            'type' =>
                'deduction',

            'category' =>
                $isrConcept->category,

            'source' =>
                $isrConcept->source,

            'unit' =>
                $isrConcept->unit,

            'sat_key' =>
                $isrConcept->sat_key,

            'quantity' =>
                1,

            'rate' =>
                $isrAmount,

            'amount' =>
                $isrAmount,

            'taxable_amount' =>
                null,

            'exempt_amount' =>
                null,

            'reduces_taxable_base' =>
                false,

            'metadata' => [
                'fiscal_calculation' => true,

                'calculation_mode' =>
                    $run->calculationMode(),

                'run_type' =>
                    $run->run_type,

                'taxable_before_wage_reduction' =>
                    $preview[
                        'taxable_before_wage_reduction'
                    ],

                'wage_reduction_amount' =>
                    $preview[
                        'wage_reduction_amount'
                    ],

                'wage_reduction_codes' =>
                    $preview[
                        'wage_reduction_codes'
                    ],

                'taxable_income' =>
                    $preview[
                        'taxable_income'
                    ],

                'exempt_income' =>
                    $preview[
                        'exempt_income'
                    ],

                'isr_before_subsidy' =>
                    $preview[
                        'isr_before_subsidy'
                    ],

                /*
                 * BEXIA_V5836_FISCAL_SNAPSHOT_SUBSIDY_CAUSED
                 */
                'subsidy_caused' =>
                    $preview[
                        'subsidy_caused'
                    ],

                'subsidy_applied' =>
                    $preview[
                        'subsidy_applied'
                    ],

                'current_isr_withheld' =>
                    $currentIsrAmount,

                'isr_adjustment' =>
                    $isrAdjustment,

                'subsidy_reconciliation' =>
                    $reconciliation,

                'isr_tariff_code' =>
                    $preview[
                        'isr_tariff_code'
                    ],

                'subsidy_rule_code' =>
                    $preview[
                        'subsidy_rule_code'
                    ],
            ],

            'sort_order' =>
                (int) (
                    $isrConcept->sort_order
                    ?: 300
                ),
        ]);

        $operationalDeductions =
            round(
                (float) $line->deductions_amount,
                2
            );

        $fiscalDeductions =
            round(
                $operationalDeductions
                + $isrAmount,
                2
            );

        $fiscalNet =
            round(
                max(
                    0,
                    (float) $line->gross_amount
                    - $fiscalDeductions
                ),
                2
            );

        $details = is_array($line->details)
            ? $line->details
            : [];

        $details['fiscal'] = [
            'calculation_mode' =>
                $run->calculationMode(),

            'run_type' =>
                $run->run_type,

            'operational_deductions' =>
                $operationalDeductions,

            'taxable_before_wage_reduction' =>
                $preview[
                    'taxable_before_wage_reduction'
                ],

            'wage_reduction_amount' =>
                $preview[
                    'wage_reduction_amount'
                ],

            'wage_reduction_codes' =>
                $preview[
                    'wage_reduction_codes'
                ],

            'taxable_income' =>
                $preview[
                    'taxable_income'
                ],

            'exempt_income' =>
                $preview[
                    'exempt_income'
                ],

            'prior_month_taxable_income' =>
                $preview[
                    'prior_month_taxable_income'
                ],

            'month_taxable_including_current' =>
                $preview[
                    'month_taxable_including_current'
                ],

            'isr_before_subsidy' =>
                $preview[
                    'isr_before_subsidy'
                ],

            'prior_month_subsidy_applied' =>
                $preview[
                    'prior_month_subsidy_applied'
                ],

            'monthly_subsidy_remaining' =>
                $preview[
                    'monthly_subsidy_remaining'
                ],

            'subsidy_caused' =>
                $preview[
                    'subsidy_caused'
                ],

            'subsidy_applied' =>
                $preview[
                    'subsidy_applied'
                ],

            'isr_withheld' =>
                $isrAmount,

            'isr_tariff_code' =>
                $preview[
                    'isr_tariff_code'
                ],

            'subsidy_rule_code' =>
                $preview[
                    'subsidy_rule_code'
                ],

            'fiscal_deductions' =>
                $fiscalDeductions,

            'fiscal_net' =>
                $fiscalNet,
        ];

        $line->forceFill([
            'deductions_amount' =>
                $fiscalDeductions,

            'net_amount' =>
                $fiscalNet,

            'details' =>
                $details,
        ])->save();

        return [
            'employee_id' =>
                (int) $employee->id,

            'payroll_run_line_id' =>
                (int) $line->id,

            'operational_deductions' =>
                $operationalDeductions,

            'taxable_income' =>
                (float) $preview[
                    'taxable_income'
                ],

            'exempt_income' =>
                (float) $preview[
                    'exempt_income'
                ],

            'isr_before_subsidy' =>
                (float) $preview[
                    'isr_before_subsidy'
                ],

            'subsidy_caused' =>
                (float) $preview[
                    'subsidy_caused'
                ],

            'subsidy_applied' =>
                (float) $preview[
                    'subsidy_applied'
                ],

            'isr_withheld' =>
                $isrAmount,

            'isr_tariff_code' =>
                $preview[
                    'isr_tariff_code'
                ],

            'subsidy_rule_code' =>
                $preview[
                    'subsidy_rule_code'
                ],

            'snapshot' => [
                'taxable_before_wage_reduction' =>
                    $preview[
                        'taxable_before_wage_reduction'
                    ],

                'wage_reduction_amount' =>
                    $preview[
                        'wage_reduction_amount'
                    ],

                'wage_reduction_codes' =>
                    $preview[
                        'wage_reduction_codes'
                    ],

                'prior_month_taxable_income' =>
                    $preview[
                        'prior_month_taxable_income'
                    ],

                'month_taxable_including_current' =>
                    $preview[
                        'month_taxable_including_current'
                    ],

                'prior_month_subsidy_caused' =>
                    $preview[
                        'prior_month_subsidy_caused'
                    ],

                'prior_month_subsidy_applied' =>
                    $preview[
                        'prior_month_subsidy_applied'
                    ],

                /*
                 * Subsidio causado se congela en el snapshot
                 * aunque la columna principal siga siendo
                 * subsidy_applied por compatibilidad.
                 */
                'subsidy_caused' =>
                    $preview[
                        'subsidy_caused'
                    ],

                'monthly_subsidy_remaining' =>
                    $preview[
                        'monthly_subsidy_remaining'
                    ],

                'operational_deductions' =>
                    $operationalDeductions,

                'fiscal_deductions' =>
                    $fiscalDeductions,

                'fiscal_net' =>
                    $fiscalNet,

                'current_isr_withheld' =>
                    $currentIsrAmount,

                'isr_adjustment' =>
                    $isrAdjustment,

                'subsidy_reconciliation' =>
                    $reconciliation,
            ],
        ];
    }

    public static function persistForVersion(
        PayrollRun $run,
        PayrollRunVersion $version,
        array $rows
    ): void {
        if (! $run->calculatesFiscalTaxes()) {
            return;
        }

        foreach ($rows as $row) {
            PayrollFiscalAccumulatorService
                ::storeRunResult(
                    run: $run,
                    version: $version,

                    employeeId:
                        (int) $row[
                            'employee_id'
                        ],

                    payrollRunLineId:
                        (int) $row[
                            'payroll_run_line_id'
                        ],

                    taxableIncome:
                        (float) $row[
                            'taxable_income'
                        ],

                    exemptIncome:
                        (float) $row[
                            'exempt_income'
                        ],

                    isrBeforeSubsidy:
                        (float) $row[
                            'isr_before_subsidy'
                        ],

                    /*
                     * BEXIA_V5836_STORE_RUN_SUBSIDY_CAUSED
                     */
                    subsidyCaused:
                        (float) $row[
                            'subsidy_caused'
                        ],

                    subsidyApplied:
                        (float) $row[
                            'subsidy_applied'
                        ],

                    isrWithheld:
                        (float) $row[
                            'isr_withheld'
                        ],

                    isrTariffCode:
                        $row[
                            'isr_tariff_code'
                        ],

                    subsidyRuleCode:
                        $row[
                            'subsidy_rule_code'
                        ],

                    snapshot:
                        $row[
                            'snapshot'
                        ]
                );

            /*
             * No reconstruimos el acumulado mensual aquí.
             *
             * En este punto la corrida sólo está CALCULATED.
             * El acumulado fiscal definitivo se actualiza cuando
             * una corrida fiscal_cfdi queda APPROVED.
             */
        }
    }
}
