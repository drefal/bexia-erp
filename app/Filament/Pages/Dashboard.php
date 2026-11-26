<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // BEXIA_PERF10_DASHBOARD_LITE

    protected static string $view =
        'filament.pages.dashboard-lite';

    public function getWidgets(): array
    {
        return [];
    }
}
