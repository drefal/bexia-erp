<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class CxcCustomerStatementReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Cuentas por cobrar';
    protected static ?string $navigationLabel = 'Estados de cuenta clientes';
    protected static ?string $title = 'Estados de cuenta cliente';
    protected static ?int $navigationSort = 40;
    protected static ?string $slug = 'cxc/reportes/estado-clientes';
    protected static string $view = 'filament.pages.cxc-customer-statement-report';

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'pages.cxccustomerstatementreport',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::userCanReports();
    }

    protected static function userCanReports(): bool
    {
        try {
            $tenant = Filament::getTenant();

            if ($tenant && method_exists($tenant, 'getKey') && class_exists(PermissionRegistrar::class)) {
                app(PermissionRegistrar::class)->setPermissionsTeamId((int) $tenant->getKey());
            }
        } catch (\Throwable $e) {
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'can') && (
            $user->can('account_receivables.view')
            || $user->can('account_receivables.collect')
            || $user->can('account_receivables.update')
            || $user->can('account_receivables.create')
            || $user->can('account_receivables.aging')
            || $user->can('account_receivables.customer_statement')
        )) {
            return true;
        }

        if (method_exists($user, 'getRoleNames')) {
            try {
                foreach ($user->getRoleNames() as $roleName) {
                    $normalized = strtolower((string) $roleName);

                    if (
                        str_contains($normalized, 'super')
                        || str_contains($normalized, 'admin')
                        || str_contains($normalized, 'administrador')
                    ) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return false;
    }

    public function tenantId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant && method_exists($tenant, 'getKey')
            ? (int) $tenant->getKey()
            : null;
    }

    public function defaultDateFrom(): string
    {
        return now()->subMonths(3)->toDateString();
    }

    public function defaultDateTo(): string
    {
        return now()->toDateString();
    }

    public function customerOptions(): array
    {
        $tenantId = $this->tenantId();

        if (! $tenantId) {
            return [];
        }

        return DB::table('contacts')
            ->where('company_id', $tenantId)
            ->where('is_customer', true)
            ->where(function ($query): void {
                $query->whereNull('is_active')
                    ->orWhere('is_active', true);
            })
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'rfc'])
            ->mapWithKeys(function ($contact): array {
                $label = trim((string) $contact->name);

                if (! empty($contact->rfc)) {
                    $label .= ' (' . $contact->rfc . ')';
                }

                return [(int) $contact->id => $label];
            })
            ->all();
    }

    public function receivables(): Collection
    {
        $tenantId = $this->tenantId();

        if (! $tenantId) {
            return collect();
        }

        $customerContactId = (int) request('customer_contact_id', 0);
        $documentSearch = trim((string) request('document_search', ''));
        $dateFrom = (string) request('date_from', $this->defaultDateFrom());
        $dateTo = (string) request('date_to', $this->defaultDateTo());

        $query = DB::table('account_receivables')
            ->where('company_id', $tenantId)
            ->orderBy('customer_name')
            ->orderBy('issue_date')
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

        if ($dateFrom !== '') {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        return $query->get([
            'id',
            'number',
            'customer_contact_id',
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

    public function payments(): Collection
    {
        $tenantId = $this->tenantId();

        if (! $tenantId) {
            return collect();
        }

        $customerContactId = (int) request('customer_contact_id', 0);
        $documentSearch = trim((string) request('document_search', ''));
        $dateFrom = (string) request('date_from', $this->defaultDateFrom());
        $dateTo = (string) request('date_to', $this->defaultDateTo());

        $query = DB::table('account_receivable_payments as p')
            ->join('account_receivables as ar', 'ar.id', '=', 'p.account_receivable_id')
            ->leftJoin('accounting_entries as e', 'e.id', '=', 'p.accounting_entry_id')
            ->where('p.company_id', $tenantId)
            ->orderBy('ar.customer_name')
            ->orderBy('p.payment_date')
            ->orderBy('p.id');

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

    public function totals(): array
    {
        $receivables = $this->receivables();
        $payments = $this->payments();

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
}
