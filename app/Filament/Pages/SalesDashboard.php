<?php

namespace App\Filament\Pages;

use App\Services\Sales\SalesDashboardService;
use Filament\Pages\Page;

class SalesDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Dashboard de ventas';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Dashboard de ventas';

    protected static string $view = 'filament.pages.sales-dashboard';

    public string $scope = 'company';

    public string $channel = 'all';

    public ?string $companyId = null;

    public ?string $branchId = null;

    public string $period = 'month';

    public string $granularity = 'day';

    public string $comparison = 'previous_period';

    public string $from = '';

    public string $until = '';

    public function mount(): void
    {
        $this->applyPeriod('month');

        $options = $this->getCompanyOptionsProperty();
        $tenantId = $this->tenantCompanyId();

        $this->companyId = $tenantId && array_key_exists((string) $tenantId, $options)
            ? (string) $tenantId
            : (array_key_first($options) !== null
                ? (string) array_key_first($options)
                : null);
    }

    public function updatedPeriod(string $value): void
    {
        if ($value !== 'custom') {
            $this->applyPeriod($value);
        }

        unset($this->dashboardData);
    }

    public function updatedCompanyId(): void
    {
        $allowed = $this->getCompanyOptionsProperty();

        if (
            $this->companyId !== null
            && ! array_key_exists((string) $this->companyId, $allowed)
        ) {
            $tenantId = $this->tenantCompanyId();

            $this->companyId = $tenantId
                ? (string) $tenantId
                : null;
        }

        $this->branchId = null;
        unset($this->dashboardData);
        unset($this->branchOptions);
    }

    public function updatedBranchId(): void
    {
        unset($this->dashboardData);
    }

    public function updatedChannel(): void
    {
        unset($this->dashboardData);
    }

    public function updatedGranularity(): void
    {
        unset($this->dashboardData);
    }

    public function updatedComparison(): void
    {
        unset($this->dashboardData);
    }

    public function updatedFrom(): void
    {
        unset($this->dashboardData);
    }

    public function updatedUntil(): void
    {
        unset($this->dashboardData);
    }

    public function refreshDashboard(): void
    {
        unset($this->dashboardData);

        $this->dispatch('sales-dashboard-refreshed');
    }

    public function getDashboardDataProperty(): array
    {
        return app(SalesDashboardService::class)->build([
            'scope' => $this->scope,
            'channel' => $this->channel,
            'tenant_company_id' => $this->tenantCompanyId(),
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'period' => $this->period,
            'granularity' => $this->granularity,
            'comparison' => $this->comparison,
            'from' => $this->from,
            'until' => $this->until,
        ]);
    }

    public function getCompanyOptionsProperty(): array
    {
        $tenantId = $this->tenantCompanyId();

        if (! $tenantId) {
            return [];
        }

        $tenant = \Illuminate\Support\Facades\DB::table('companies')
            ->where('id', $tenantId)
            ->first(['id', 'company_group_id']);

        if (! $tenant) {
            return [];
        }

        $query = \Illuminate\Support\Facades\DB::table('companies')
            ->where('active', true);

        if ($tenant->company_group_id) {
            $query->where('company_group_id', $tenant->company_group_id);
        } else {
            $query->where('id', $tenantId);
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(
                fn ($name, $id) => [(string) $id => $name]
            )
            ->all();
    }

    public function getBranchOptionsProperty(): array
    {
        return app(SalesDashboardService::class)->branchOptions(
            $this->companyId ? (int) $this->companyId : null
        );
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        return (int) $tenant->getKey();
    }

    protected function applyPeriod(string $period): void
    {
        $today = now();

        [$from, $until] = match ($period) {
            'today' => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ],

            'yesterday' => [
                $today->copy()->subDay()->startOfDay(),
                $today->copy()->subDay()->endOfDay(),
            ],

            'week' => [
                $today->copy()->startOfWeek(),
                $today->copy()->endOfWeek(),
            ],

            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],

            'year' => [
                $today->copy()->startOfYear(),
                $today->copy()->endOfYear(),
            ],

            default => [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
            ],
        };

        $this->from = $from->toDateString();
        $this->until = $until->toDateString();
    }
}
