<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BexiaNoticesSummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 30;

    public static function canView(): bool
    {
        return static::widgetVisible('notices');
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardWidgetRegistry::class)->metrics('notices');

        return [
            Stat::make('Avisos sin leer', (int) ($metrics['unread'] ?? 0))
                ->description('Notificaciones pendientes de revisar')
                ->color(((int) ($metrics['unread'] ?? 0)) > 0 ? 'warning' : 'success'),

            Stat::make('Avisos totales', (int) ($metrics['total'] ?? 0))
                ->description('Historial de avisos del usuario')
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
}
