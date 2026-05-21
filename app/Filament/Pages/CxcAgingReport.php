<?php

namespace App\Filament\Pages;

use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CxcAgingReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationGroup = 'Cuentas por cobrar';
    protected static ?string $navigationLabel = 'Antigüedad de saldos';
    protected static ?string $title = 'Antigüedad de saldos';
    protected static ?int $navigationSort = 30;
    protected static ?string $slug = 'cxc/reportes/antiguedad';
    protected static string $view = 'filament.pages.cxc-aging-report';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::userCanReports();
    }

    protected static function userCanReports(): bool
    {
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
        )) {
            return true;
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

    public function defaultAsOfDate(): string
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

    public function rows(): Collection
    {
        $tenantId = $this->tenantId();

        if (! $tenantId) {
            return collect();
        }

        $asOf = Carbon::parse(request('as_of_date', $this->defaultAsOfDate()))->startOfDay();
        $customerContactId = (int) request('customer_contact_id', 0);
        $documentSearch = trim((string) request('document_search', ''));

        $query = DB::table('account_receivables')
            ->where('company_id', $tenantId)
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
                $row->bucket_label = match ($bucket) {
                    'not_due' => 'Por vencer',
                    'days_1_30' => '1 a 30 días',
                    'days_31_60' => '31 a 60 días',
                    'days_61_90' => '61 a 90 días',
                    'days_90_plus' => 'Más de 90 días',
                    default => $bucket,
                };

                return $row;
            });
    }

    public function summary(): array
    {
        $rows = $this->rows();

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
}
