<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BexiaPayrollCfdiSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 60;

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('payroll_cfdi_summary');
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardWidgetRegistry::class)->metrics('payroll_cfdi_summary');

        $validated = (int) ($metrics['validated'] ?? 0);
        $stamped = (int) ($metrics['stamped'] ?? 0);
        $internal = (int) ($metrics['internal_only'] ?? 0);
        $notRequired = (int) ($metrics['cfdi_not_required'] ?? 0);
        $external = (int) ($metrics['external_stamped'] ?? 0);
        $errors = (int) (($metrics['error'] ?? 0) + ($metrics['stamping_error'] ?? 0));

        return [
            Stat::make('CFDI validados', $validated)
                ->description('Recibos validados sin timbrado SAT')
                ->color('info'),

            Stat::make('CFDI timbrados', $stamped + $external)
                ->description('Timbrados por PAC o externos')
                ->color('success'),

            Stat::make('Internos / no requeridos', $internal + $notRequired)
                ->description('Recibos sin CFDI fiscal')
                ->color('gray'),

            Stat::make('Errores CFDI', $errors)
                ->description('Recibos con error')
                ->color($errors > 0 ? 'danger' : 'success'),
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
