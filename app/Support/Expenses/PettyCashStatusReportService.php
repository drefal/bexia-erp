<?php

namespace App\Support\Expenses;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PettyCashStatusReportService
{
    public static function rows(int $companyId): Collection
    {
        return DB::table('petty_cash_funds as f')
            ->leftJoin('employees as e', 'e.id', '=', 'f.employee_id')
            ->leftJoin(
                'treasury_accounts as ta',
                'ta.id',
                '=',
                'f.treasury_account_id'
            )
            ->where('f.company_id', $companyId)
            ->select([
                'f.id',
                'f.number',
                'f.name',
                'f.employee_id',
                'e.name as employee_name',
                'f.authorized_amount',
                'f.operational_balance',
                'f.currency_code',
                'f.status',
                'f.is_active',
                'f.assigned_at',
                'ta.current_balance as treasury_balance',
            ])
            ->orderBy('f.name')
            ->get()
            ->map(function ($row) {
                $movementTotals = DB::table(
                    'petty_cash_fund_movements'
                )
                    ->where('petty_cash_fund_id', $row->id)
                    ->selectRaw("
                        COALESCE(
                            SUM(
                                CASE
                                    WHEN type = 'initial_funding'
                                    THEN amount
                                    ELSE 0
                                END
                            ),
                            0
                        ) AS initial_funding,

                        COALESCE(
                            SUM(
                                CASE
                                    WHEN type = 'replenishment'
                                    THEN amount
                                    ELSE 0
                                END
                            ),
                            0
                        ) AS replenishment,

                        COALESCE(
                            SUM(
                                CASE
                                    WHEN type = 'expense'
                                    THEN amount
                                    ELSE 0
                                END
                            ),
                            0
                        ) AS expense,

                        COALESCE(
                            SUM(
                                CASE
                                    WHEN type = 'return'
                                    THEN amount
                                    ELSE 0
                                END
                            ),
                            0
                        ) AS returned,

                        MAX(movement_date) AS last_movement_date
                    ")
                    ->first();

                $operational = round(
                    (float) $row->operational_balance,
                    6
                );

                $treasury = round(
                    (float) ($row->treasury_balance ?? 0),
                    6
                );

                $row->difference = round(
                    $operational - $treasury,
                    6
                );

                $row->has_difference =
                    abs($row->difference) > 0.000001;

                $row->initial_funding_total =
                    (float) ($movementTotals->initial_funding ?? 0);

                $row->replenishment_total =
                    (float) ($movementTotals->replenishment ?? 0);

                $row->expense_total =
                    (float) ($movementTotals->expense ?? 0);

                $row->return_total =
                    (float) ($movementTotals->returned ?? 0);

                $row->last_movement_date =
                    $movementTotals->last_movement_date ?? null;

                return $row;
            });
    }

    public static function summary(Collection $rows): array
    {
        return [
            'funds' => $rows->count(),
            'authorized' => (float) $rows->sum(
                'authorized_amount'
            ),
            'operational' => (float) $rows->sum(
                'operational_balance'
            ),
            'treasury' => (float) $rows->sum(
                'treasury_balance'
            ),
            'differences' => $rows
                ->where('has_difference', true)
                ->count(),
            'initial_funding' => (float) $rows->sum(
                'initial_funding_total'
            ),
            'expenses' => (float) $rows->sum(
                'expense_total'
            ),
            'replenishments' => (float) $rows->sum(
                'replenishment_total'
            ),
            'returns' => (float) $rows->sum(
                'return_total'
            ),
        ];
    }

    public static function writeExcel(
        string $path,
        int $companyId
    ): void {
        $rows = static::rows($companyId);

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Estado cajas chicas');

        $writer->addRow(
            \OpenSpout\Common\Entity\Row::fromValues([
                'Folio',
                'Caja chica',
                'Responsable',
                'Monto autorizado',
                'Saldo operativo',
                'Saldo Tesorería',
                'Diferencia',
                'Fondeo inicial',
                'Reposiciones',
                'Gastos',
                'Devoluciones',
                'Último movimiento',
                'Estado',
                'Integridad',
            ])
        );

        foreach ($rows as $row) {
            $writer->addRow(
                \OpenSpout\Common\Entity\Row::fromValues([
                    $row->number,
                    $row->name,
                    $row->employee_name,
                    (float) $row->authorized_amount,
                    (float) $row->operational_balance,
                    (float) ($row->treasury_balance ?? 0),
                    (float) $row->difference,
                    (float) $row->initial_funding_total,
                    (float) $row->replenishment_total,
                    (float) $row->expense_total,
                    (float) $row->return_total,
                    $row->last_movement_date,
                    $row->status,
                    $row->has_difference
                        ? 'REVISAR'
                        : 'OK',
                ])
            );
        }

        $writer->close();
    }
}
