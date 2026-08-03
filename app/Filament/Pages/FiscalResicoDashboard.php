<?php

namespace App\Filament\Pages;

use App\Support\FiscalSat\FiscalSatAccess;
use App\Support\FiscalSat\ResicoTaxCalculator;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class FiscalResicoDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Fiscal SAT';

    protected static ?string $navigationLabel = 'Dashboard RESICO';

    protected static ?string $title = 'Dashboard Fiscal RESICO';

    protected static ?int $navigationSort = 16;

    protected static string $view = 'filament.pages.fiscal-resico-dashboard';

    public ?string $period = null;

    public array $periodOptions = [];

    public array $report = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $companyId = $this->companyId();

        if (! $companyId) {
            return;
        }

        $calculator = app(ResicoTaxCalculator::class);
        $this->periodOptions = $calculator->availablePeriods($companyId);
        $this->period = array_key_first($this->periodOptions) ?: now()->format('Y-m');

        $this->refreshReport();
    }

    public function updatedPeriod(): void
    {
        $this->refreshReport();
    }

    public function refreshReport(): void
    {
        $companyId = $this->companyId();

        if (! $companyId || ! $this->period) {
            $this->report = [];

            return;
        }

        $this->report = app(ResicoTaxCalculator::class)->calculate($companyId, $this->period);
    }

    private function companyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        return app(ResicoTaxCalculator::class)->defaultCompanyId();
    }

    public static function canAccess(): bool
    {
        return FiscalSatAccess::canAny([
            'fiscal_sat.menu.view',
            'fiscal_sat.tax_summary.view',
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
