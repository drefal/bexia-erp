<?php

namespace App\Support\Expenses;

use Illuminate\Support\Collection;

class ExpenseAnalyticsReportService
{
    public static function filteredLines(
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $type = null,
        ?string $status = null,
        ?int $employeeId = null,
        ?int $categoryId = null,
        ?string $supplier = null,
        array $selectedLineIds = []
    ): Collection {
        return ExpenseDetailReportService::rows(
            $companyId,
            $dateFrom,
            $dateTo,
            $type,
            $status,
            $employeeId,
            $categoryId,
            $supplier,
            $selectedLineIds
        );
    }

    public static function byEmployee(Collection $lines): Collection
    {
        return $lines
            ->groupBy(
                fn ($row) =>
                    (int) ($row->spent_by_employee_id ?? 0)
            )
            ->map(function (Collection $items) {
                $first = $items->first();

                $total = (float) $items->sum('total_amount');

                return (object) [
                    'employee_id' =>
                        (int) ($first->spent_by_employee_id ?? 0),

                    'employee_name' =>
                        $first->spent_by_name ?: 'Sin empleado',

                    'expense_count' =>
                        $items->count(),

                    'subtotal' =>
                        (float) $items->sum('subtotal'),

                    'tax' =>
                        (float) $items->sum('tax_amount'),

                    'total' =>
                        $total,

                    'average' =>
                        $items->count() > 0
                            ? $total / $items->count()
                            : 0,

                    'petty_cash_total' =>
                        (float) $items
                            ->where('report_type', 'petty_cash')
                            ->sum('total_amount'),

                    'reimbursement_total' =>
                        (float) $items
                            ->where('report_type', 'reimbursement')
                            ->sum('total_amount'),

                    'with_receipt' =>
                        $items
                            ->filter(
                                fn ($r) =>
                                    (bool) $r->has_receipt
                            )
                            ->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    public static function byCategory(Collection $lines): Collection
    {
        $grandTotal = (float) $lines->sum('total_amount');

        return $lines
            ->groupBy(
                fn ($row) =>
                    (int) ($row->expense_category_id ?? 0)
            )
            ->map(function (Collection $items) use ($grandTotal) {
                $first = $items->first();

                $total = (float) $items->sum('total_amount');

                return (object) [
                    'category_id' =>
                        (int) ($first->expense_category_id ?? 0),

                    'category_name' =>
                        $first->category_name
                        ?: 'Sin categoría',

                    'expense_count' =>
                        $items->count(),

                    'employees' =>
                        $items
                            ->pluck('spent_by_employee_id')
                            ->filter()
                            ->unique()
                            ->count(),

                    'subtotal' =>
                        (float) $items->sum('subtotal'),

                    'tax' =>
                        (float) $items->sum('tax_amount'),

                    'total' =>
                        $total,

                    'percentage' =>
                        $grandTotal > 0
                            ? ($total / $grandTotal) * 100
                            : 0,

                    'with_receipt' =>
                        $items
                            ->filter(
                                fn ($r) =>
                                    (bool) $r->has_receipt
                            )
                            ->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    public static function employeeSummary(
        Collection $rows
    ): array {
        return [
            'employees' =>
                $rows->count(),

            'expenses' =>
                (int) $rows->sum('expense_count'),

            'subtotal' =>
                (float) $rows->sum('subtotal'),

            'tax' =>
                (float) $rows->sum('tax'),

            'total' =>
                (float) $rows->sum('total'),
        ];
    }

    public static function categorySummary(
        Collection $rows
    ): array {
        return [
            'categories' =>
                $rows->count(),

            'expenses' =>
                (int) $rows->sum('expense_count'),

            'subtotal' =>
                (float) $rows->sum('subtotal'),

            'tax' =>
                (float) $rows->sum('tax'),

            'total' =>
                (float) $rows->sum('total'),
        ];
    }

    public static function writeEmployeeExcel(
        string $path,
        Collection $rows
    ): void {
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);

        $writer
            ->getCurrentSheet()
            ->setName('Gastos por empleado');

        $writer->addRow(
            \OpenSpout\Common\Entity\Row::fromValues([
                'Empleado',
                'Gastos',
                'Subtotal',
                'IVA',
                'Total',
                'Promedio',
                'Caja chica',
                'Reembolsos',
                'Con comprobante',
            ])
        );

        foreach ($rows as $row) {
            $writer->addRow(
                \OpenSpout\Common\Entity\Row::fromValues([
                    $row->employee_name,
                    $row->expense_count,
                    $row->subtotal,
                    $row->tax,
                    $row->total,
                    $row->average,
                    $row->petty_cash_total,
                    $row->reimbursement_total,
                    $row->with_receipt,
                ])
            );
        }

        $writer->close();
    }

    public static function writeCategoryExcel(
        string $path,
        Collection $rows
    ): void {
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);

        $writer
            ->getCurrentSheet()
            ->setName('Gastos por categoria');

        $writer->addRow(
            \OpenSpout\Common\Entity\Row::fromValues([
                'Categoría',
                'Gastos',
                'Empleados',
                'Subtotal',
                'IVA',
                'Total',
                'Participación %',
                'Con comprobante',
            ])
        );

        foreach ($rows as $row) {
            $writer->addRow(
                \OpenSpout\Common\Entity\Row::fromValues([
                    $row->category_name,
                    $row->expense_count,
                    $row->employees,
                    $row->subtotal,
                    $row->tax,
                    $row->total,
                    round($row->percentage, 2),
                    $row->with_receipt,
                ])
            );
        }

        $writer->close();
    }
}
