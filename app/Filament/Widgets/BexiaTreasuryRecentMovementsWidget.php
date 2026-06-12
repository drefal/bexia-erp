<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaTreasuryRecentMovementsWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-treasury-recent-movements-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 85;

    // BEXIA_V5727N29F_TREASURY_POLLING_10MIN_NO_SPATIE_INFO
    protected static ?string $pollingInterval = '600s';

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('treasury_recent_movements');
    }

    protected function getViewData(): array
    {
        $companyId = app(DashboardWidgetRegistry::class)->currentCompanyId();
        $rows = collect();

        if ($companyId && Schema::hasTable('treasury_movements')) {
            $rows = DB::table('treasury_movements as tm')
                ->leftJoin('treasury_accounts as ta', 'ta.id', '=', 'tm.treasury_account_id')
                ->where('tm.company_id', $companyId)
                ->select([
                    'tm.id',
                    'tm.type',
                    'tm.status',
                    'tm.amount',
                    'tm.reference',
                    'tm.created_at',
                    'tm.posted_at',
                    'ta.name as account_name',
                    'ta.cash_scope',
                ])
                ->orderByDesc('tm.id')
                ->limit(10)
                ->get();
        }

        return [
            'rows' => $rows,
            'updatedAt' => now()->format('H:i:s'),
        ];
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
