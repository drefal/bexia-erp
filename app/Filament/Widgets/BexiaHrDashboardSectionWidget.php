<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardSectionData;
use Filament\Widgets\Widget;

class BexiaHrDashboardSectionWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-hr-dashboard-section-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 39;

    public static function canView(): bool
    {
        return app(DashboardSectionData::class)->widgetVisible('hr_dashboard_section');
    }

    protected function getViewData(): array
    {
        return app(DashboardSectionData::class)->hr();
    }
}
