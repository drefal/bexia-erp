<?php

namespace App\Http\Controllers\Reports\Cxc;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class AccountReceivableReportExportController extends Controller
{
    public function agingPdf(Request $request, string|int $tenant): Response
    {
        $companyId = (int) $tenant;
        $this->setPermissionTenant($companyId);
        $this->authorizeReport();
        $rows = $this->agingRows($companyId, $request);
        $summary = $this->agingSummary($rows);
        $company = $this->company($companyId);

        $pdf = Pdf::loadView('exports.cxc.account-receivable-aging-pdf', [
            'company' => $company,
            'logoSrc' => $this->companyLogoSrc($company),
            'rows' => $rows,
            'summary' => $summary,
            'asOfDate' => $request->query('as_of_date', now()->toDateString()),
            'customerContactId' => (int) $request->query('customer_contact_id', 0),
            'documentSearch' => trim((string) $request->query('document_search', '')),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('antiguedad-saldos-cxc-' . now()->format('Ymd-His') . '.pdf');
    }

    public function agingExcel(Request $request, string|int $tenant): Response
    {
        $companyId = (int) $tenant;
        $this->setPermissionTenant($companyId);
        $this->authorizeReport();
        $rows = $this->agingRows($companyId, $request);
        $summary = $this->agingSummary($rows);
        $company = $this->company($companyId);

        $html = view('exports.cxc.account-receivable-aging-excel', [
            'company' => $company,
            'rows' => $rows,
            'summary' => $summary,
            'asOfDate' => $request->query('as_of_date', now()->toDateString()),
            'customerContactId' => (int) $request->query('customer_contact_id', 0),
            'documentSearch' => trim((string) $request->query('document_search', '')),
        ])->render();

        return response("\xEF\xBB\xBF" . $html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="antiguedad-saldos-cxc-' . now()->format('Ymd-His') . '.xls"',
        ]);
    }

    public function customerPdf(Request $request, string|int $tenant): Response
    {
        $companyId = (int) $tenant;
        $this->setPermissionTenant($companyId);
        $this->authorizeReport();
        $receivables = $this->customerReceivables($companyId, $request);
        $payments = $this->customerPayments($companyId, $request);
        $totals = $this->customerTotals($receivables, $payments);
        $company = $this->company($companyId);

        $pdf = Pdf::loadView('exports.cxc.customer-statement-pdf', [
            'company' => $company,
            'logoSrc' => $this->companyLogoSrc($company),
            'receivables' => $receivables,
            'payments' => $payments,
            'totals' => $totals,
            'customerContactId' => (int) $request->query('customer_contact_id', 0),
            'dateFrom' => $request->query('date_from', now()->subMonths(3)->toDateString()),
            'dateTo' => $request->query('date_to', now()->toDateString()),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('estado-cuenta-clientes-cxc-' . now()->format('Ymd-His') . '.pdf');
    }

    public function customerExcel(Request $request, string|int $tenant): Response
    {
        $companyId = (int) $tenant;
        $this->setPermissionTenant($companyId);
        $this->authorizeReport();
        $receivables = $this->customerReceivables($companyId, $request);
        $payments = $this->customerPayments($companyId, $request);
        $totals = $this->customerTotals($receivables, $payments);
        $company = $this->company($companyId);

        $html = view('exports.cxc.customer-statement-excel', [
            'company' => $company,
            'receivables' => $receivables,
            'payments' => $payments,
            'totals' => $totals,
            'customerContactId' => (int) $request->query('customer_contact_id', 0),
            'dateFrom' => $request->query('date_from', now()->subMonths(3)->toDateString()),
            'dateTo' => $request->query('date_to', now()->toDateString()),
        ])->render();

        return response("\xEF\xBB\xBF" . $html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="estado-cuenta-clientes-cxc-' . now()->format('Ymd-His') . '.xls"',
        ]);
    }

    protected function agingRows(int $companyId, Request $request): Collection
    {
        $asOf = Carbon::parse($request->query('as_of_date', now()->toDateString()))->startOfDay();
        $customerContactId = (int) $request->query('customer_contact_id', 0);
        $documentSearch = trim((string) $request->query('document_search', ''));

        $query = DB::table('account_receivables')
            ->where('company_id', $companyId)
            ->whereIn('status', ['open', 'partial'])
            ->where('balance_total', '>', 0)
            ->orderBy('due_date')
            ->orderBy('number');

        if ($customerContactId > 0) {
            $query->where('customer_contact_id', $customerContactId);
        }

        if ($documentSearch !== '') {
            $query->where(function ($q) use ($documentSearch): void {
                $q->where('number', 'ilike', '%' . $documentSearch . '%')
                    ->orWhere('customer_reference', 'ilike', '%' . $documentSearch . '%');
            });
        }

        return $query
            ->get([
                'id',
                'number',
                'customer_contact_id',
                'customer_name',
                'customer_reference',
                'issue_date',
                'due_date',
                'currency',
                'total',
                'collected_total',
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

    protected function customerReceivables(int $companyId, Request $request): Collection
    {
        $customerContactId = (int) $request->query('customer_contact_id', 0);
        $dateFrom = (string) $request->query('date_from', now()->subMonths(3)->toDateString());
        $dateTo = (string) $request->query('date_to', now()->toDateString());

        $query = DB::table('account_receivables')
            ->where('company_id', $companyId)
            ->orderBy('customer_name')
            ->orderBy('issue_date')
            ->orderBy('number');

        $documentSearch = trim((string) $request->query('document_search', ''));

        if ($customerContactId > 0) {
            $query->where('customer_contact_id', $customerContactId);
        }

        if ($documentSearch !== '') {
            $query->where(function ($q) use ($documentSearch): void {
                $q->where('number', 'ilike', '%' . $documentSearch . '%')
                    ->orWhere('customer_reference', 'ilike', '%' . $documentSearch . '%');
            });
        }

        if ($dateFrom !== '') {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        return $query->get([
            'id',
            'number',
            'customer_name',
            'customer_reference',
            'issue_date',
            'due_date',
            'currency',
            'status',
            'total',
            'collected_total',
            'balance_total',
        ]);
    }

    protected function customerPayments(int $companyId, Request $request): Collection
    {
        $customerContactId = (int) $request->query('customer_contact_id', 0);
        $dateFrom = (string) $request->query('date_from', now()->subMonths(3)->toDateString());
        $dateTo = (string) $request->query('date_to', now()->toDateString());

        $query = DB::table('account_receivable_payments as p')
            ->join('account_receivables as ar', 'ar.id', '=', 'p.account_receivable_id')
            ->leftJoin('accounting_entries as e', 'e.id', '=', 'p.accounting_entry_id')
            ->where('p.company_id', $companyId)
            ->orderBy('ar.customer_name')
            ->orderBy('p.payment_date')
            ->orderBy('p.id');

        $documentSearch = trim((string) $request->query('document_search', ''));

        if ($customerContactId > 0) {
            $query->where('ar.customer_contact_id', $customerContactId);
        }

        if ($documentSearch !== '') {
            $query->where(function ($q) use ($documentSearch): void {
                $q->where('ar.number', 'ilike', '%' . $documentSearch . '%')
                    ->orWhere('ar.customer_reference', 'ilike', '%' . $documentSearch . '%')
                    ->orWhere('p.reference', 'ilike', '%' . $documentSearch . '%');
            });
        }

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
            'ar.number as receivable_number',
            'ar.customer_name as customer_name',
            'e.entry_number as accounting_entry_number',
        ]);
    }

    protected function customerTotals(Collection $receivables, Collection $payments): array
    {
        return [
            'receivables_total' => (float) $receivables->sum(fn ($row) => (float) $row->total),
            'collected_total' => (float) $receivables->sum(fn ($row) => (float) $row->collected_total),
            'balance_total' => (float) $receivables->sum(fn ($row) => (float) $row->balance_total),
            'payments_total' => (float) $payments
                ->where('status', 'posted')
                ->sum(fn ($row) => (float) $row->amount),
            'cancelled_payments_total' => (float) $payments
                ->where('status', 'cancelled')
                ->sum(fn ($row) => (float) $row->amount),
        ];
    }

    protected function bucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'not_due' => 'Por vencer',
            'days_1_30' => '1-30 días',
            'days_31_60' => '31-60 días',
            'days_61_90' => '61-90 días',
            'days_90_plus' => '+90 días',
            default => $bucket,
        };
    }

    protected function company(int $companyId): ?object
    {
        return DB::table('companies')->where('id', $companyId)->first();
    }

    protected function authorizeReport(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if (method_exists($user, 'can') && (
            $user->can('account_receivables.view')
            || $user->can('account_receivables.collect')
            || $user->can('account_receivables.update')
            || $user->can('account_receivables.create')
            || $user->can('account_receivables.aging')
            || $user->can('account_receivables.customer_statement')
        )) {
            return;
        }

        if (method_exists($user, 'getRoleNames')) {
            try {
                foreach ($user->getRoleNames() as $roleName) {
                    $normalized = strtolower((string) $roleName);

                    if (
                        str_contains($normalized, 'super')
                        && (
                            str_contains($normalized, 'admin')
                            || str_contains($normalized, 'administrador')
                        )
                    ) {
                        return;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        abort(403);
    }

    protected function setPermissionTenant(int $tenant): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant);
        }
    }

    protected function companyLogoSrc(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        foreach (['logo_url', 'logo_path', 'logo', 'image_path', 'image', 'avatar'] as $field) {
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

            foreach ([$value, 'storage/' . ltrim($value, '/'), 'images/' . ltrim($value, '/'), 'logos/' . ltrim($value, '/')] as $candidate) {
                $path = public_path(ltrim($candidate, '/'));

                if (is_file($path) && is_readable($path)) {
                    $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';

                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        return null;
    }
}
