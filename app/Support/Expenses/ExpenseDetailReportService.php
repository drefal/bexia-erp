<?php

namespace App\Support\Expenses;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseDetailReportService
{
    public static function employeeOptions(int $companyId): array
    {
        return DB::table('employees')
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->all();
    }

    public static function categoryOptions(int $companyId): array
    {
        return DB::table('expense_categories')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->all();
    }

    public static function projectOptions(int $companyId): array
    {
        return DB::table('expense_projects')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(
                fn ($row) => [
                    (int) $row->id =>
                        trim(
                            ($row->code ?: '')
                            . ($row->code ? ' · ' : '')
                            . ($row->name ?: '')
                        ),
                ]
            )
            ->all();
    }

    public static function typeOptions(): array
    {
        return [
            'petty_cash' => 'Caja chica',
            'reimbursement' => 'Reembolso',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'pending_approval' => 'Pendiente aprobación',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'paid' => 'Pagado',
            'closed' => 'Cerrado',
            'cancelled' => 'Cancelado',
        ];
    }

    public static function rows(
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $type = null,
        ?string $status = null,
        ?int $employeeId = null,
        ?int $categoryId = null,
        ?int $projectId = null,
        ?string $supplier = null,
        array $selectedLineIds = []
    ): Collection {
        $q = DB::table('expense_report_lines as l')
            ->join(
                'expense_reports as r',
                'r.id',
                '=',
                'l.expense_report_id'
            )
            ->leftJoin(
                'employees as se',
                'se.id',
                '=',
                'l.spent_by_employee_id'
            )
            ->leftJoin(
                'employees as re',
                're.id',
                '=',
                'r.employee_id'
            )
            ->leftJoin(
                'expense_categories as c',
                'c.id',
                '=',
                'l.expense_category_id'
            )
            ->leftJoin(
                'expense_projects as p',
                'p.id',
                '=',
                'l.expense_project_id'
            )
            ->leftJoin(
                'petty_cash_funds as f',
                'f.id',
                '=',
                'r.petty_cash_fund_id'
            )
            ->where('r.company_id', $companyId)
            ->select([
                'l.id',
                'l.expense_report_id',
                'r.number as report_number',
                'r.type as report_type',
                'r.status as report_status',
                'r.report_date',
                'r.employee_id as report_employee_id',
                're.name as report_employee_name',
                'r.petty_cash_fund_id',
                'f.number as fund_number',
                'f.name as fund_name',

                'l.expense_date',
                'l.spent_by_employee_id',
                'se.name as spent_by_name',
                'l.expense_category_id',
                'c.name as category_name',
                'l.expense_project_id',
                'p.code as project_code',
                'p.name as project_name',
                'l.supplier_name',
                'l.supplier_rfc',
                'l.description',
                'l.subtotal',
                'l.tax_amount',
                'l.total_amount',
                'l.currency_code',
                'l.payment_method',
                'l.cfdi_uuid',
                'l.sat_cfdi_document_id',
                'l.has_receipt',
                'l.requires_receipt',
                'l.receipt_exception_reason',
            ]);

        if ($dateFrom) {
            $q->whereDate('l.expense_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $q->whereDate('l.expense_date', '<=', $dateTo);
        }

        if ($type) {
            $q->where('r.type', $type);
        }

        if ($status) {
            $q->where('r.status', $status);
        }

        if ($employeeId !== null) {
            $q->where(
                'l.spent_by_employee_id',
                $employeeId
            );
        }

        if ($categoryId) {
            $q->where('l.expense_category_id', $categoryId);
        }

        if ($projectId) {
            $q->where('l.expense_project_id', $projectId);
        }

        if ($supplier && trim($supplier) !== '') {
            $q->where(
                'l.supplier_name',
                'ilike',
                '%' . trim($supplier) . '%'
            );
        }

        if (! empty($selectedLineIds)) {
            $ids = collect($selectedLineIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (! empty($ids)) {
                $q->whereIn('l.id', $ids);
            }
        }

        return $q
            ->orderByDesc('l.expense_date')
            ->orderByDesc('l.id')
            ->get()
            ->map(function ($row) {
                $row->type_label =
                    static::typeOptions()[$row->report_type]
                    ?? $row->report_type;

                $row->status_label =
                    static::statusOptions()[$row->report_status]
                    ?? $row->report_status;

                return $row;
            });
    }

    public static function selectableRows(
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $type = null,
        ?string $status = null,
        ?int $employeeId = null,
        ?int $categoryId = null,
        ?int $projectId = null,
        ?string $supplier = null
    ): Collection {
        return static::rows(
            $companyId,
            $dateFrom,
            $dateTo,
            $type,
            $status,
            $employeeId,
            $categoryId,
            $projectId,
            $supplier,
            []
        );
    }

    public static function optionLabel(object $row): string
    {
        $parts = [
            (string) ($row->report_number ?: ('#' . $row->expense_report_id)),
            (string) ($row->description ?: 'Sin descripción'),
            (string) ($row->spent_by_name ?: 'Sin empleado'),
            '$' . number_format((float) $row->total_amount, 2),
        ];

        return implode(' · ', $parts);
    }

    public static function summary(Collection $rows): array
    {
        return [
            'lines' => $rows->count(),
            'subtotal' => (float) $rows->sum('subtotal'),
            'tax' => (float) $rows->sum('tax_amount'),
            'total' => (float) $rows->sum('total_amount'),
            'with_receipt' => $rows
                ->filter(fn ($r) => (bool) $r->has_receipt)
                ->count(),
        ];
    }

    public static function writeExcel(
        string $path,
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $type = null,
        ?string $status = null,
        ?int $employeeId = null,
        ?int $categoryId = null,
        ?int $projectId = null,
        ?string $supplier = null,
        array $selectedLineIds = []
    ): void {
        $rows = static::rows(
            $companyId,
            $dateFrom,
            $dateTo,
            $type,
            $status,
            $employeeId,
            $categoryId,
            $projectId,
            $supplier,
            $selectedLineIds
        );

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Comprobaciones');

        $writer->addRow(
            \OpenSpout\Common\Entity\Row::fromValues([
                'Fecha',
                'Comprobación',
                'Tipo',
                'Estado',
                'Caja chica',
                'Empleado',
                'Categoría',
                'Proyecto',
                'Proveedor',
                'RFC',
                'Descripción',
                'Subtotal',
                'IVA',
                'Total',
                'Método pago',
                'UUID CFDI',
                'Comprobante',
            ])
        );

        foreach ($rows as $row) {
            $writer->addRow(
                \OpenSpout\Common\Entity\Row::fromValues([
                    $row->expense_date,
                    $row->report_number,
                    $row->type_label,
                    $row->status_label,
                    trim(
                        ($row->fund_number ?: '')
                        . ' '
                        . ($row->fund_name ?: '')
                    ),
                    $row->spent_by_name,
                    $row->category_name,
                    trim(
                        ($row->project_code ?: '')
                        . ($row->project_code ? ' · ' : '')
                        . ($row->project_name ?: '')
                    ),
                    $row->supplier_name,
                    $row->supplier_rfc,
                    $row->description,
                    (float) $row->subtotal,
                    (float) $row->tax_amount,
                    (float) $row->total_amount,
                    $row->payment_method,
                    $row->cfdi_uuid,
                    $row->has_receipt ? 'Sí' : 'No',
                ])
            );
        }

        $writer->close();
    }
}
