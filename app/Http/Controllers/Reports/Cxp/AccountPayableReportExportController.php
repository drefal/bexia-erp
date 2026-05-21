<?php

namespace App\Http\Controllers\Reports\Cxp;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AccountPayableReportExportController extends Controller
{
    public function agingPdf(Request $request, string|int $tenant): Response
    {
        $this->authorizeReport();

        $companyId = (int) $tenant;
        $rows = $this->agingRows($companyId, $request);
        $summary = $this->agingSummary($rows);
        $company = $this->company($companyId);

        $pdf = Pdf::loadView('exports.cxp.account-payable-aging-pdf', [
            'company' => $company,
            'logoSrc' => $this->companyLogoSrc($company),
            'rows' => $rows,
            'summary' => $summary,
            'asOfDate' => $request->query('as_of_date', now()->toDateString()),
            'supplierLabel' => $this->supplierLabel($companyId, (string) $request->query('supplier_key', '')),
            'documentSearch' => trim((string) $request->query('document_search', '')),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('antiguedad-saldos-cxp-' . now()->format('Ymd-His') . '.pdf');
    }

    public function agingExcel(Request $request, string|int $tenant): Response
    {
        $this->authorizeReport();

        $companyId = (int) $tenant;
        $rows = $this->agingRows($companyId, $request);
        $summary = $this->agingSummary($rows);
        $company = $this->company($companyId);

        $html = view('exports.cxp.account-payable-aging-excel', [
            'company' => $company,
            'rows' => $rows,
            'summary' => $summary,
            'asOfDate' => $request->query('as_of_date', now()->toDateString()),
            'supplierLabel' => $this->supplierLabel($companyId, (string) $request->query('supplier_key', '')),
            'documentSearch' => trim((string) $request->query('document_search', '')),
        ])->render();

        return response("\xEF\xBB\xBF" . $html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="antiguedad-saldos-cxp-' . now()->format('Ymd-His') . '.xls"',
        ]);
    }

    public function supplierPdf(Request $request, string|int $tenant): Response
    {
        $this->authorizeReport();

        $companyId = (int) $tenant;
        $payables = $this->supplierPayables($companyId, $request);
        $payments = $this->supplierPayments($companyId, $request);
        $totals = $this->supplierTotals($payables, $payments);
        $company = $this->company($companyId);

        $pdf = Pdf::loadView('exports.cxp.supplier-statement-pdf', [
            'company' => $company,
            'logoSrc' => $this->companyLogoSrc($company),
            'payables' => $payables,
            'payments' => $payments,
            'totals' => $totals,
            'supplierLabel' => $this->supplierLabel($companyId, (string) $request->query('supplier_key', '')),
            'dateFrom' => $request->query('date_from', now()->subMonths(3)->toDateString()),
            'dateTo' => $request->query('date_to', now()->toDateString()),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('estado-cuenta-proveedor-cxp-' . now()->format('Ymd-His') . '.pdf');
    }

    public function supplierExcel(Request $request, string|int $tenant): Response
    {
        $this->authorizeReport();

        $companyId = (int) $tenant;
        $payables = $this->supplierPayables($companyId, $request);
        $payments = $this->supplierPayments($companyId, $request);
        $totals = $this->supplierTotals($payables, $payments);
        $company = $this->company($companyId);

        $html = view('exports.cxp.supplier-statement-excel', [
            'company' => $company,
            'payables' => $payables,
            'payments' => $payments,
            'totals' => $totals,
            'supplierLabel' => $this->supplierLabel($companyId, (string) $request->query('supplier_key', '')),
            'dateFrom' => $request->query('date_from', now()->subMonths(3)->toDateString()),
            'dateTo' => $request->query('date_to', now()->toDateString()),
        ])->render();

        return response("\xEF\xBB\xBF" . $html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="estado-cuenta-proveedor-cxp-' . now()->format('Ymd-His') . '.xls"',
        ]);
    }

    protected function agingRows(int $companyId, Request $request): Collection
    {
        $asOf = Carbon::parse($request->query('as_of_date', now()->toDateString()))->startOfDay();
        $supplierKey = trim((string) $request->query('supplier_key', ''));
        $documentSearch = trim((string) $request->query('document_search', ''));

        $query = DB::table('account_payables')
            ->where('company_id', $companyId)
            ->whereIn('status', ['open', 'partial'])
            ->where('balance_total', '>', 0)
            ->orderBy('due_date')
            ->orderBy('number');

        $this->applySupplierFilter($query, $supplierKey);

        if ($documentSearch !== '') {
            $query->where(function ($q) use ($documentSearch): void {
                $q->where('number', 'ilike', '%' . $documentSearch . '%')
                    ->orWhere('supplier_reference', 'ilike', '%' . $documentSearch . '%');
            });
        }

        return $query
            ->get([
                'id',
                'number',
                'supplier_contact_id',
                'supplier_name',
                'supplier_reference',
                'issue_date',
                'due_date',
                'currency',
                'total',
                'paid_total',
                'balance_total',
                'status',
            ])
            ->map(function ($row) use ($asOf) {
                $dueDate = $row->due_date
                    ? Carbon::parse($row->due_date)->startOfDay()
                    : ($row->issue_date ? Carbon::parse($row->issue_date)->startOfDay() : $asOf->copy());

                $daysOverdue = $dueDate->greaterThanOrEqualTo($asOf)
                    ? 0
                    : (int) $dueDate->diffInDays($asOf);

                $bucket = match (true) {
                    $daysOverdue <= 0 => 'not_due',
                    $daysOverdue <= 30 => 'days_1_30',
                    $daysOverdue <= 60 => 'days_31_60',
                    $daysOverdue <= 90 => 'days_61_90',
                    default => 'days_90_plus',
                };

                $row->days_overdue = $daysOverdue;
                $row->bucket = $bucket;
                $row->bucket_label = $this->bucketLabel($bucket);

                return $row;
            });
    }

    protected function agingSummary(Collection $rows): array
    {
        $summary = [
            'total' => 0.0,
            'not_due' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
        ];

        foreach ($rows as $row) {
            $amount = (float) $row->balance_total;
            $summary['total'] += $amount;
            $summary[$row->bucket] += $amount;
        }

        return $summary;
    }

    protected function supplierPayables(int $companyId, Request $request): Collection
    {
        $supplierKey = trim((string) $request->query('supplier_key', ''));
        $dateFrom = (string) $request->query('date_from', now()->subMonths(3)->toDateString());
        $dateTo = (string) $request->query('date_to', now()->toDateString());

        $query = DB::table('account_payables')
            ->where('company_id', $companyId)
            ->orderBy('issue_date')
            ->orderBy('number');

        $this->applySupplierFilter($query, $supplierKey);

        if ($dateFrom !== '') {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        return $query->get([
            'id',
            'number',
            'supplier_name',
            'issue_date',
            'due_date',
            'currency',
            'status',
            'total',
            'paid_total',
            'balance_total',
        ]);
    }

    protected function supplierPayments(int $companyId, Request $request): Collection
    {
        $supplierKey = trim((string) $request->query('supplier_key', ''));
        $dateFrom = (string) $request->query('date_from', now()->subMonths(3)->toDateString());
        $dateTo = (string) $request->query('date_to', now()->toDateString());

        $query = DB::table('account_payable_payments as p')
            ->join('account_payables as ap', 'ap.id', '=', 'p.account_payable_id')
            ->leftJoin('accounting_entries as e', 'e.id', '=', 'p.accounting_entry_id')
            ->where('p.company_id', $companyId)
            ->orderBy('p.payment_date')
            ->orderBy('p.id');

        $this->applySupplierFilter($query, $supplierKey, 'ap');

        if ($dateFrom !== '') {
            $query->whereDate('p.payment_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('p.payment_date', '<=', $dateTo);
        }

        return $query->get([
            'p.id',
            'p.status',
            'p.amount',
            'p.currency',
            'p.payment_date',
            'p.reference',
            'p.accounting_entry_id',
            'ap.number as payable_number',
            'ap.supplier_name as supplier_name',
            'e.entry_number',
        ]);
    }

    protected function supplierTotals(Collection $payables, Collection $payments): array
    {
        return [
            'documents_total' => (float) $payables->sum('total'),
            'paid_total' => (float) $payables->sum('paid_total'),
            'balance_total' => (float) $payables->sum('balance_total'),
            'payments_total' => (float) $payments->where('status', 'posted')->sum('amount'),
        ];
    }

    protected function applySupplierFilter($query, string $supplierKey, string $alias = ''): void
    {
        if ($supplierKey === '') {
            return;
        }

        $prefix = $alias !== '' ? $alias . '.' : '';

        if (ctype_digit($supplierKey)) {
            $query->where($prefix . 'supplier_contact_id', (int) $supplierKey);
            return;
        }

        $query->where($prefix . 'supplier_name', $supplierKey);
    }

    protected function supplierLabel(int $companyId, string $supplierKey): string
    {
        $supplierKey = trim($supplierKey);

        if ($supplierKey === '') {
            return 'Todos los proveedores';
        }

        $query = DB::table('account_payables')->where('company_id', $companyId);
        $this->applySupplierFilter($query, $supplierKey);

        return (string) ($query->value('supplier_name') ?: $supplierKey);
    }

    protected function company(int $companyId): object
    {
        return DB::table('companies')->where('id', $companyId)->first()
            ?? (object) ['id' => $companyId, 'name' => 'Empresa'];
    }

    protected function companyLogoSrc(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        $fields = ['logo_url', 'logo_path', 'logo', 'image_path', 'image', 'avatar'];

        foreach ($fields as $field) {
            $value = isset($company->{$field}) ? trim((string) $company->{$field}) : '';

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'data:image')) {
                return $value;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            $candidates = [
                $value,
                'storage/' . ltrim($value, '/'),
                'images/' . ltrim($value, '/'),
                'logos/' . ltrim($value, '/'),
            ];

            foreach ($candidates as $candidate) {
                $path = public_path(ltrim($candidate, '/'));

                if (is_file($path) && is_readable($path)) {
                    $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        return null;
    }

    protected function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'not_due' => 'Por vencer',
            'days_1_30' => '1 a 30 días',
            'days_31_60' => '31 a 60 días',
            'days_61_90' => '61 a 90 días',
            'days_90_plus' => 'Más de 90 días',
            default => 'Sin clasificar',
        };
    }

    protected function authorizeReport(): void
    {
        abort_unless(auth()->check(), 403);
    }
}
