<?php

namespace App\Filament\Pages;

use App\Models\SatCfdiDocument;
use App\Models\SatDownloadRequest;
use App\Support\FiscalSat\FiscalSatAccess;
use Filament\Pages\Page;

class FiscalSatDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Panel fiscal';

    protected static ?string $title = 'Panel fiscal SAT';

    protected static ?string $navigationGroup = 'Fiscal SAT';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.fiscal-sat-dashboard';

    public array $stats = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return FiscalSatAccess::canAny([
            'fiscal_sat.menu.view',
            'fiscal_sat.cfdi.view',
            'fiscal_sat.tax_summary.view',
        ]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $companyIds = FiscalSatAccess::allowedCompanyIds(auth()->user());

        $documents = SatCfdiDocument::query()->whereIn('company_id', $companyIds);
        $requests = SatDownloadRequest::query()->whereIn('company_id', $companyIds);

        $this->stats = [
            'companies_count' => count($companyIds),
            'documents_count' => (clone $documents)->count(),
            'issued_count' => (clone $documents)->where('direction', 'issued')->count(),
            'received_count' => (clone $documents)->where('direction', 'received')->count(),
            'issued_total' => (float) (clone $documents)->where('direction', 'issued')->sum('total'),
            'received_total' => (float) (clone $documents)->where('direction', 'received')->sum('total'),
            'pending_requests_count' => (clone $requests)->whereIn('status', ['draft', 'requested', 'processing', 'pending'])->count(),
        ];
    }
}
