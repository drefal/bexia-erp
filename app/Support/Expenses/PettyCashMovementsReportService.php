<?php

namespace App\Support\Expenses;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PettyCashMovementsReportService
{
    public static function fundOptions(int $companyId): array
    {
        return DB::table('petty_cash_funds')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'number', 'name'])
            ->mapWithKeys(function ($row): array {
                return [
                    (int) $row->id =>
                        trim(($row->number ?: '') . ' · ' . ($row->name ?: '')),
                ];
            })
            ->all();
    }

    public static function typeOptions(): array
    {
        return [
            'initial_funding' => 'Fondeo inicial',
            'replenishment' => 'Reposición',
            'expense' => 'Gasto',
            'return' => 'Devolución',
        ];
    }

    public static function rows(
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $fundId = null,
        ?string $type = null
    ): Collection {
        $query = DB::table('petty_cash_fund_movements as m')
            ->join(
                'petty_cash_funds as f',
                'f.id',
                '=',
                'm.petty_cash_fund_id'
            )
            ->leftJoin(
                'employees as e',
                'e.id',
                '=',
                'f.employee_id'
            )
            ->leftJoin(
                'expense_reports as er',
                'er.id',
                '=',
                'm.expense_report_id'
            )
            ->leftJoin(
                'users as u',
                'u.id',
                '=',
                'm.created_by_user_id'
            )
            ->where('m.company_id', $companyId)
            ->select([
                'm.id',
                'm.petty_cash_fund_id',
                'f.number as fund_number',
                'f.name as fund_name',
                'e.name as employee_name',
                'm.expense_report_id',
                'er.number as expense_report_number',
                'm.type',
                'm.movement_date',
                'm.amount',
                'm.currency_code',
                'm.balance_before',
                'm.balance_after',
                'm.reference',
                'm.description',
                'm.treasury_movement_id',
                'm.created_by_user_id',
                'u.name as created_by_name',
                'm.created_at',
            ]);

        if ($dateFrom) {
            $query->whereDate('m.movement_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('m.movement_date', '<=', $dateTo);
        }

        if ($fundId) {
            $query->where('m.petty_cash_fund_id', $fundId);
        }

        if ($type) {
            $query->where('m.type', $type);
        }

        return $query
            ->orderByDesc('m.movement_date')
            ->orderByDesc('m.id')
            ->get()
            ->map(function ($row) {
                $row->type_label = static::typeLabel(
                    (string) $row->type
                );

                $row->is_entry = in_array(
                    (string) $row->type,
                    [
                        'initial_funding',
                        'replenishment',
                    ],
                    true
                );

                $row->is_exit = in_array(
                    (string) $row->type,
                    [
                        'expense',
                        'return',
                    ],
                    true
                );

                $row->entry_amount = $row->is_entry
                    ? (float) $row->amount
                    : 0.0;

                $row->exit_amount = $row->is_exit
                    ? (float) $row->amount
                    : 0.0;

                return $row;
            });
    }

    public static function summary(Collection $rows): array
    {
        $first = $rows
            ->sortBy([
                ['movement_date', 'asc'],
                ['id', 'asc'],
            ])
            ->first();

        $last = $rows
            ->sortBy([
                ['movement_date', 'asc'],
                ['id', 'asc'],
            ])
            ->last();

        return [
            'movements' => $rows->count(),
            'entries' => (float) $rows->sum('entry_amount'),
            'exits' => (float) $rows->sum('exit_amount'),
            'opening_balance' => $first
                ? (float) $first->balance_before
                : 0.0,
            'closing_balance' => $last
                ? (float) $last->balance_after
                : 0.0,
        ];
    }

    public static function typeLabel(string $type): string
    {
        return static::typeOptions()[$type]
            ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function writeExcel(
        string $path,
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $fundId = null,
        ?string $type = null
    ): void {
        $rows = static::rows(
            $companyId,
            $dateFrom,
            $dateTo,
            $fundId,
            $type
        );

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Movimientos caja chica');

        $writer->addRow(
            \OpenSpout\Common\Entity\Row::fromValues([
                'Fecha',
                'Folio caja',
                'Caja chica',
                'Responsable',
                'Tipo',
                'Referencia',
                'Descripción',
                'Entrada',
                'Salida',
                'Saldo anterior',
                'Saldo posterior',
                'Comprobación',
                'Movimiento Tesorería',
                'Usuario',
            ])
        );

        foreach ($rows as $row) {
            $writer->addRow(
                \OpenSpout\Common\Entity\Row::fromValues([
                    $row->movement_date,
                    $row->fund_number,
                    $row->fund_name,
                    $row->employee_name,
                    $row->type_label,
                    $row->reference,
                    $row->description,
                    (float) $row->entry_amount,
                    (float) $row->exit_amount,
                    (float) $row->balance_before,
                    (float) $row->balance_after,
                    $row->expense_report_number,
                    $row->treasury_movement_id,
                    $row->created_by_name,
                ])
            );
        }

        $writer->close();
    }
}
