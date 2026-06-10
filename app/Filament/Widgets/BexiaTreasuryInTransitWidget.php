<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaTreasuryInTransitWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-treasury-in-transit-widget';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 84;

    // BEXIA_V5727N29F_TREASURY_POLLING_10MIN_NO_SPATIE_INFO
    protected static ?string $pollingInterval = '600s';

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('treasury_in_transit');
    }

    protected function getViewData(): array
    {
        $companyId = app(DashboardWidgetRegistry::class)->currentCompanyId();
        $rows = collect();

        if ($companyId && Schema::hasTable('treasury_cash_transfer_requests')) {
            $rows = DB::table('treasury_cash_transfer_requests as r')
                ->leftJoin('treasury_accounts as src', 'src.id', '=', 'r.source_treasury_account_id')
                ->leftJoin('treasury_accounts as dst', 'dst.id', '=', 'r.destination_treasury_account_id')
                ->where('r.company_id', $companyId)
                ->whereNull('r.posted_at')
                ->whereIn('r.status', ['requested', 'pending_approval', 'approved', 'delivered', 'received'])
                ->select([
                    'r.id',
                    'r.number',
                    'r.type',
                    'r.status',
                    'r.approval_status',
                    'r.amount',
                    'r.created_at',
                    'src.name as source_name',
                    'dst.name as destination_name',
                ])
                ->orderByDesc('r.id')
                ->limit(8)
                ->get();
        }

        return [
            'rows' => $rows,
            'total' => (float) $rows->sum(fn ($row): float => (float) ($row->amount ?? 0)),
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
