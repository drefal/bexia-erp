<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BexiaPayrollRunsSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 50;

    public static function canView(): bool
    {
        // BEXIA_V57210G2_DASHBOARD_WIDGET_PERMISSION
        if (! \App\Support\Security\BexiaAccess::dashboard()) {
            return false;
        }

        return static::bexiaDashboardWidgetVisible('payroll_runs_summary');
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardWidgetRegistry::class)->metrics('payroll_runs_summary');

        return [
            Stat::make('Nóminas cerradas', (int) ($metrics['closed'] ?? 0))
                ->description('Corridas cerradas')
                ->color('success'),

            Stat::make('Nóminas aprobadas', (int) ($metrics['approved'] ?? 0))
                ->description('Con aprobación completada')
                ->color('info'),

            Stat::make('Neto acumulado', '$ ' . number_format((float) ($metrics['net_total'] ?? 0), 2))
                ->description('Total neto de nóminas')
                ->color('gray'),
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
