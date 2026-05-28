<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BexiaPayrollAccountingSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 70;

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('payroll_accounting_summary');
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardWidgetRegistry::class)->metrics('payroll_accounting_summary');

        return [
            Stat::make('Nóminas contabilizadas', (int) ($metrics['posted'] ?? 0))
                ->description('Pólizas activas de nómina')
                ->color(((int) ($metrics['posted'] ?? 0)) > 0 ? 'success' : 'gray'),

            Stat::make('Pendientes de póliza', (int) ($metrics['pending_payroll_runs'] ?? 0))
                ->description('Nóminas cerradas sin póliza activa')
                ->color(((int) ($metrics['pending_payroll_runs'] ?? 0)) > 0 ? 'warning' : 'success'),

            Stat::make('Pólizas reversadas', (int) ($metrics['reversals'] ?? 0))
                ->description('Reversas contables de nómina')
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
