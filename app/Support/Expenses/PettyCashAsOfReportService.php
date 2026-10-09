<?php

namespace App\Support\Expenses;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PettyCashAsOfReportService
{
    public static function fundOptions(
        int $companyId,
        ?int $employeeId = null
    ): array {
        return DB::table('petty_cash_funds')
            ->where('company_id', $companyId)
            ->when(
                $employeeId !== null,
                fn ($query) =>
                    $query->where(
                        'employee_id',
                        $employeeId
                    )
            )
            ->orderBy('name')
            ->get([
                'id',
                'number',
                'name',
            ])
            ->mapWithKeys(
                fn ($row) => [
                    (int) $row->id =>
                        trim(
                            ($row->number ?: '')
                            . ' · '
                            . ($row->name ?: '')
                        ),
                ]
            )
            ->all();
    }

    public static function rows(
        int $companyId,
        string $asOfDate,
        ?int $fundId = null,
        ?int $employeeId = null
    ): Collection {
        $funds = DB::table('petty_cash_funds as f')
            ->leftJoin(
                'employees as e',
                'e.id',
                '=',
                'f.employee_id'
            )
            ->where('f.company_id', $companyId)
            ->when(
                $employeeId !== null,
                fn ($query) =>
                    $query->where(
                        'f.employee_id',
                        $employeeId
                    )
            )
            ->where(function ($q) use ($asOfDate): void {
                $q->whereNull('f.assigned_at')
                    ->orWhereDate(
                        'f.assigned_at',
                        '<=',
                        $asOfDate
                    );
            })
            ->when(
                $fundId,
                fn ($q) =>
                    $q->where('f.id', $fundId)
            )
            ->select([
                'f.id',
                'f.number',
                'f.name',
                'f.employee_id',
                'e.name as employee_name',
                'f.authorized_amount',
                'f.currency_code',
                'f.status',
                'f.is_active',
                'f.assigned_at',
                'f.closed_at',
            ])
            ->orderBy('f.name')
            ->get();

        return $funds->map(
            function ($fund) use ($asOfDate) {
                $movements = DB::table(
                    'petty_cash_fund_movements'
                )
                    ->where(
                        'petty_cash_fund_id',
                        $fund->id
                    )
                    ->whereDate(
                        'movement_date',
                        '<=',
                        $asOfDate
                    );

                $totals = (clone $movements)
                    ->selectRaw("
                        COUNT(*) AS movement_count,

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
                        ) AS returned
                    ")
                    ->first();

                $last = (clone $movements)
                    ->orderByDesc('movement_date')
                    ->orderByDesc('id')
                    ->first([
                        'id',
                        'movement_date',
                        'type',
                        'amount',
                        'balance_after',
                        'reference',
                    ]);

                $fund->movement_count =
                    (int) ($totals->movement_count ?? 0);

                $fund->initial_funding_total =
                    (float) ($totals->initial_funding ?? 0);

                $fund->replenishment_total =
                    (float) ($totals->replenishment ?? 0);

                $fund->expense_total =
                    (float) ($totals->expense ?? 0);

                $fund->return_total =
                    (float) ($totals->returned ?? 0);

                $fund->balance_as_of =
                    $last
                        ? (float) $last->balance_after
                        : 0.0;

                $fund->last_movement_id =
                    $last?->id;

                $fund->last_movement_date =
                    $last?->movement_date;

                $fund->last_movement_type =
                    $last?->type;

                $fund->last_movement_reference =
                    $last?->reference;

                $fund->status_as_of =
                    static::statusAsOf(
                        $fund,
                        $asOfDate
                    );

                return $fund;
            }
        );
    }

    public static function summary(
        Collection $rows
    ): array {
        return [
            'funds' =>
                $rows->count(),

            'authorized' =>
                (float) $rows->sum(
                    'authorized_amount'
                ),

            'balance' =>
                (float) $rows->sum(
                    'balance_as_of'
                ),

            'initial_funding' =>
                (float) $rows->sum(
                    'initial_funding_total'
                ),

            'replenishments' =>
                (float) $rows->sum(
                    'replenishment_total'
                ),

            'expenses' =>
                (float) $rows->sum(
                    'expense_total'
                ),

            'returns' =>
                (float) $rows->sum(
                    'return_total'
                ),
        ];
    }

    public static function typeLabel(
        ?string $type
    ): string {
        return match ($type) {
            'initial_funding' =>
                'Fondeo inicial',

            'replenishment' =>
                'Reposición',

            'expense' =>
                'Gasto',

            'return' =>
                'Devolución',

            null, '' =>
                'Sin movimientos',

            default =>
                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $type
                    )
                ),
        };
    }

    public static function statusAsOf(
        object $fund,
        string $asOfDate
    ): string {
        if (
            ! empty($fund->closed_at)
            && (string) $fund->closed_at <= $asOfDate
        ) {
            return 'Cerrada';
        }

        if (
            ! empty($fund->assigned_at)
            && (string) $fund->assigned_at > $asOfDate
        ) {
            return 'No asignada aún';
        }

        return 'Vigente a la fecha';
    }

    public static function writeExcel(
        string $path,
        int $companyId,
        string $asOfDate,
        ?int $fundId = null,
        ?int $employeeId = null
    ): void {
        $rows = static::rows(
            $companyId,
            $asOfDate,
            $fundId,
            $employeeId
        );

        $writer =
            new \OpenSpout\Writer\XLSX\Writer();

        $writer->openToFile($path);

        $writer
            ->getCurrentSheet()
            ->setName('Caja chica a fecha');

        $writer->addRow(
            \OpenSpout\Common\Entity\Row::fromValues([
                'Folio',
                'Caja chica',
                'Responsable',
                'Fecha corte',
                'Autorizado',
                'Saldo a fecha',
                'Fondeo inicial',
                'Reposiciones',
                'Gastos',
                'Devoluciones',
                'Movimientos',
                'Último movimiento',
                'Tipo último movimiento',
                'Referencia',
                'Estado a fecha',
            ])
        );

        foreach ($rows as $row) {
            $writer->addRow(
                \OpenSpout\Common\Entity\Row::fromValues([
                    $row->number,
                    $row->name,
                    $row->employee_name,
                    $asOfDate,
                    (float) $row->authorized_amount,
                    (float) $row->balance_as_of,
                    (float) $row->initial_funding_total,
                    (float) $row->replenishment_total,
                    (float) $row->expense_total,
                    (float) $row->return_total,
                    $row->movement_count,
                    $row->last_movement_date,
                    static::typeLabel(
                        $row->last_movement_type
                    ),
                    $row->last_movement_reference,
                    $row->status_as_of,
                ])
            );
        }

        $writer->close();
    }
}
