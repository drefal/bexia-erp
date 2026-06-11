<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaTreasurySummaryWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 81;

    // BEXIA_V5727N29F_TREASURY_POLLING_10MIN_NO_SPATIE_INFO
    protected static ?string $pollingInterval = '600s';

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('treasury_summary');
    }

    protected function getStats(): array
    {
        $companyId = app(DashboardWidgetRegistry::class)->currentCompanyId();

        $cashScopes = ['pdv', 'branch_cash', 'general_cash', 'admin_cash', 'cedis_cash'];

        $cashTotal = 0.0;
        $inTransit = 0.0;
        $todayIn = 0.0;
        $todayOut = 0.0;

        if ($companyId && Schema::hasTable('treasury_accounts')) {
            $cashTotal = (float) DB::table('treasury_accounts')
                ->where('company_id', $companyId)
                ->whereIn('cash_scope', $cashScopes)
                ->where('is_active', true)
                ->sum('current_balance');
        }

        if ($companyId && Schema::hasTable('treasury_cash_transfer_requests')) {
            $inTransit = (float) DB::table('treasury_cash_transfer_requests')
                ->where('company_id', $companyId)
                ->whereNull('posted_at')
                ->whereIn('status', ['approved', 'delivered', 'received'])
                ->sum('amount');
        }

        if ($companyId && Schema::hasTable('treasury_movements')) {
            $todayIn = (float) DB::table('treasury_movements')
                ->where('company_id', $companyId)
                ->whereDate('created_at', now()->toDateString())
                ->whereIn('type', ['inflow', 'inbound'])
                ->sum('amount');

            $todayOut = (float) DB::table('treasury_movements')
                ->where('company_id', $companyId)
                ->whereDate('created_at', now()->toDateString())
                ->whereIn('type', ['outflow', 'outbound'])
                ->sum('amount');
        }

        return [
            Stat::make('Efectivo actual', static::money($cashTotal))
                ->description('Saldo en cajas operativas')
                ->icon('heroicon-o-banknotes'),

            Stat::make('En tránsito', static::money($inTransit))
                ->description('Aprobado / entregado sin aplicar')
                ->icon('heroicon-o-arrow-path'),

            Stat::make('Entradas hoy', static::money($todayIn))
                ->description('Movimientos de entrada del día')
                ->icon('heroicon-o-arrow-trending-up'),

            Stat::make('Salidas hoy', static::money($todayOut))
                ->description('Movimientos de salida del día')
                ->icon('heroicon-o-arrow-trending-down'),
        ];
    }

    protected static function money(float $amount): string
    {
        return '$ ' . number_format($amount, 2) . ' MXN';
    }

    protected static function widgetVisible(string $key): bool
    {
        try {
            return app(DashboardWidgetRegistry::class)
                ->visibleForUser(auth()->user())
                ->contains(fn (array $definition): bool => ($definition['key'] ?? null) === $key);
        } catch (\Throwable) {
            return true;
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
