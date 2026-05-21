<?php

namespace App\Filament\Pages;

use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountPayableAgingReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Antigüedad de saldos';

    protected static ?string $title = 'Antigüedad de saldos';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.account-payable-aging-report';

    public string $asOfDate = '';

    public string $supplierKey = '';

    public string $documentSearch = '';

    public function mount(): void
    {
        $this->asOfDate = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can('account_payables.view_aging');
    }

    public function getSupplierOptionsProperty(): Collection
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return collect();
        }

        return DB::table('account_payables')
            ->where('company_id', $companyId)
            ->whereIn('status', ['open', 'partial'])
            ->where('balance_total', '>', 0)
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '<>', '')
            ->selectRaw('COALESCE(supplier_contact_id::text, supplier_name) as supplier_key, supplier_name')
            ->groupBy('supplier_key', 'supplier_name')
            ->orderBy('supplier_name')
            ->get();
    }

    public function getRowsProperty(): Collection
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return collect();
        }

        $query = DB::table('account_payables')
            ->where('company_id', $companyId)
            ->whereIn('status', ['open', 'partial'])
            ->where('balance_total', '>', 0)
            ->orderBy('due_date')
            ->orderBy('number');

        $this->applySupplierFilter($query);

        $search = trim($this->documentSearch);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'ilike', '%' . $search . '%')
                    ->orWhere('supplier_reference', 'ilike', '%' . $search . '%')
                    ->orWhere('purchase_receipt_number', 'ilike', '%' . $search . '%')
                    ->orWhere('purchase_order_number', 'ilike', '%' . $search . '%');
            });
        }

        $asOf = Carbon::parse($this->asOfDate ?: now()->toDateString())->startOfDay();

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

    public function getSummaryProperty(): array
    {
        $summary = [
            'total' => 0.0,
            'not_due' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
        ];

        foreach ($this->rows as $row) {
            $amount = (float) $row->balance_total;
            $summary['total'] += $amount;
            $summary[$row->bucket] += $amount;
        }

        return $summary;
    }

    public function bucketLabel(string $bucket): string
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

    public function money(float|int|string|null $amount, string $currency = 'MXN'): string
    {
        return '$' . number_format((float) $amount, 2) . ' ' . $currency;
    }

    protected function applySupplierFilter($query): void
    {
        $key = trim($this->supplierKey);

        if ($key === '') {
            return;
        }

        if (ctype_digit($key)) {
            $query->where('supplier_contact_id', (int) $key);

            return;
        }

        $query->where('supplier_name', $key);
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant && method_exists($tenant, 'getKey')
            ? (int) $tenant->getKey()
            : null;
    }
}
