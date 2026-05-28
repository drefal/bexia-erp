<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaTreasuryCashColumnsWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-treasury-cash-columns-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 82;

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('treasury_cash_columns');
    }

    protected function getViewData(): array
    {
        $companyId = app(DashboardWidgetRegistry::class)->currentCompanyId();

        $rows = collect();

        if ($companyId && Schema::hasTable('treasury_accounts')) {
            $rows = DB::table('treasury_accounts')
                ->where('company_id', $companyId)
                ->whereIn('cash_scope', ['pdv', 'branch_cash', 'general_cash', 'admin_cash', 'cedis_cash'])
                ->where('is_active', true)
                ->orderByRaw("
                    case cash_scope
                        when 'pdv' then 10
                        when 'branch_cash' then 20
                        when 'general_cash' then 30
                        when 'admin_cash' then 40
                        when 'cedis_cash' then 50
                        else 99
                    end
                ")
                ->orderBy('name')
                ->get();
        }

        $maxBalance = max(1.0, (float) $rows->max(fn ($row): float => abs((float) ($row->current_balance ?? 0))));

        $columns = $rows->map(function ($row) use ($maxBalance): array {
            $balance = (float) ($row->current_balance ?? 0);
            $percent = $balance <= 0 ? 4 : min(100, max(8, round(($balance / $maxBalance) * 100, 2)));

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'scope' => (string) ($row->cash_scope ?? ''),
                'scope_label' => static::scopeLabel((string) ($row->cash_scope ?? '')),
                'balance' => $balance,
                'money' => '$ ' . number_format($balance, 2),
                'percent' => $percent,
                'color' => static::columnColor($percent, $balance),
            ];
        })->values();

        return [
            'columns' => $columns,
            'total' => (float) $rows->sum(fn ($row): float => (float) ($row->current_balance ?? 0)),
            'updatedAt' => now()->format('H:i:s'),
        ];
    }

    protected static function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'pdv' => 'Caja PDV',
            'branch_cash' => 'Caja sucursal',
            'general_cash' => 'Caja general',
            'admin_cash' => 'Administración',
            'cedis_cash' => 'Bodega / CEDIS',
            default => $scope !== '' ? str_replace('_', ' ', $scope) : 'Caja',
        };
    }

    protected static function columnColor(float $percent, float $balance): string
    {
        if ($balance <= 0) {
            return '#94a3b8';
        }

        return match (true) {
            $percent < 25 => '#ef4444',
            $percent < 50 => '#f59e0b',
            $percent < 80 => '#3b82f6',
            default => '#22c55e',
        };
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
