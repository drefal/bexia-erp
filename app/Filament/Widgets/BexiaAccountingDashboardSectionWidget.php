<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardSectionData;
use Filament\Widgets\Widget;

class BexiaAccountingDashboardSectionWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-accounting-dashboard-section-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 69;

    public static function canView(): bool
    {
        return app(DashboardSectionData::class)->widgetVisible('accounting_dashboard_section');
    }

    protected function getViewData(): array
    {
        return app(DashboardSectionData::class)->accounting();
    }
}
