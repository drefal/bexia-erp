<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierStatementPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Estados de cuenta proveedor';

    protected static ?string $title = 'Estados de cuenta proveedor';

    protected static ?int $navigationSort = 40;

    protected static string $view = 'filament.pages.supplier-statement-page';

    public string $supplierKey = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subMonths(3)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can('account_payables.view_supplier_statement');
    }

    public function getSupplierOptionsProperty(): Collection
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return collect();
        }

        return DB::table('account_payables')
            ->where('company_id', $companyId)
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '<>', '')
            ->selectRaw('COALESCE(supplier_contact_id::text, supplier_name) as supplier_key, supplier_name')
            ->groupBy('supplier_key', 'supplier_name')
            ->orderBy('supplier_name')
            ->get();
    }

    public function getPayablesProperty(): Collection
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return collect();
        }

        $query = DB::table('account_payables')
            ->where('company_id', $companyId)
            ->orderBy('supplier_name')
            ->orderBy('issue_date')
            ->orderBy('number');

        $this->applySupplierFilter($query);

        if ($this->dateFrom !== '') {
            $query->whereDate('issue_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('issue_date', '<=', $this->dateTo);
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

    public function getPaymentsProperty(): Collection
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return collect();
        }

        $query = DB::table('account_payable_payments as p')
            ->join('account_payables as ap', 'ap.id', '=', 'p.account_payable_id')
            ->leftJoin('accounting_entries as e', 'e.id', '=', 'p.accounting_entry_id')
            ->where('p.company_id', $companyId)
            ->orderBy('ap.supplier_name')
            ->orderBy('p.payment_date')
            ->orderBy('p.id');

        $this->applySupplierFilter($query, 'ap');

        if ($this->dateFrom !== '') {
            $query->whereDate('p.payment_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('p.payment_date', '<=', $this->dateTo);
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

    public function getTotalsProperty(): array
    {
        return [
            'documents_total' => (float) $this->payables->sum('total'),
            'paid_total' => (float) $this->payables->sum('paid_total'),
            'balance_total' => (float) $this->payables->sum('balance_total'),
            'payments_total' => (float) $this->payments->where('status', 'posted')->sum('amount'),
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'open' => 'Pendiente de pago',
            'partial' => 'Pago parcial',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
            'posted' => 'Aplicado',
            default => $status ?: 'Sin estado',
        };
    }

    public function money(float|int|string|null $amount, string $currency = 'MXN'): string
    {
        return '$' . number_format((float) $amount, 2) . ' ' . $currency;
    }

    protected function applySupplierFilter($query, string $alias = ''): void
    {
        $key = trim($this->supplierKey);

        if ($key === '') {
            return;
        }

        $prefix = $alias !== '' ? $alias . '.' : '';

        if (ctype_digit($key)) {
            $query->where($prefix . 'supplier_contact_id', (int) $key);

            return;
        }

        $query->where($prefix . 'supplier_name', $key);
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant && method_exists($tenant, 'getKey')
            ? (int) $tenant->getKey()
            : null;
    }
}
