<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BexiaHrEmployeesSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 40;

    public static function canView(): bool
    {
        return static::widgetVisible('hr_employees_summary');
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardWidgetRegistry::class)->metrics('hr_employees_summary');

        return [
            Stat::make('Empleados activos', (int) ($metrics['active'] ?? 0))
                ->description('Personal activo en la empresa')
                ->color('success'),

            Stat::make('Empleados inactivos', (int) ($metrics['inactive'] ?? 0))
                ->description('Personal inactivo')
                ->color(((int) ($metrics['inactive'] ?? 0)) > 0 ? 'warning' : 'gray'),

            Stat::make('Total empleados', (int) ($metrics['total'] ?? 0))
                ->description('Activos + inactivos')
                ->color('info'),
        ];
    }

    protected static function widgetVisible(string $key): bool
    {
        if (! auth()->check()) {
            return false;
        }

        try {
            return app(DashboardWidgetRegistry::class)
                ->visibleForUser(auth()->user())
                ->pluck('key')
                ->contains($key);
        } catch (\Throwable) {
            return false;
        }
    }
}
