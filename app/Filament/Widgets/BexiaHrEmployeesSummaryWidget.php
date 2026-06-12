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
        // BEXIA_V57210G2_DASHBOARD_WIDGET_PERMISSION
        if (! \App\Support\Security\BexiaAccess::dashboard()) {
            return false;
        }

        return static::bexiaDashboardWidgetVisible('hr_employees_summary');
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


    protected static function bexiaDashboardWidgetVisible(string $key): bool
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return true;
            }

            $registry = app(\App\Support\Dashboard\DashboardWidgetRegistry::class);
            $companyId = (int) ($registry->currentCompanyId() ?: 5);

            if (! \Illuminate\Support\Facades\Schema::hasTable('dashboard_widget_user_settings')) {
                return true;
            }

            $row = \Illuminate\Support\Facades\DB::table('dashboard_widget_user_settings')
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('widget_key', $key)
                ->first();

            if (! $row) {
                return true;
            }

            return (bool) $row->is_visible;
        } catch (\Throwable) {
            return true;
        }
    }

}
