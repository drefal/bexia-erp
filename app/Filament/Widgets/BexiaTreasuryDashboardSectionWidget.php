<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardSectionData;
use Filament\Widgets\Widget;

class BexiaTreasuryDashboardSectionWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-treasury-dashboard-section-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 80;

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        // BEXIA_V57210G2_DASHBOARD_WIDGET_PERMISSION
        if (! \App\Support\Security\BexiaAccess::dashboard()) {
            return false;
        }

        return app(DashboardSectionData::class)->widgetVisible('treasury_dashboard_section');
    }

    protected function getViewData(): array
    {
        return app(DashboardSectionData::class)->treasury();
    }
}
