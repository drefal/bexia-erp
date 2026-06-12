<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaTreasurySectionHeaderWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-treasury-section-header-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 80;

    // BEXIA_V5727N29F_TREASURY_POLLING_10MIN_NO_SPATIE_INFO
    protected static ?string $pollingInterval = '600s';

    public static function canView(): bool
    {
        // BEXIA_V57210G2_DASHBOARD_WIDGET_PERMISSION
        if (! \App\Support\Security\BexiaAccess::dashboard()) {
            return false;
        }

        return static::bexiaDashboardWidgetVisible('treasury_section_header');
    }

    protected function getViewData(): array
    {
        $companyId = app(DashboardWidgetRegistry::class)->currentCompanyId();
        $companyName = null;

        if ($companyId && Schema::hasTable('companies')) {
            $companyName = DB::table('companies')->where('id', $companyId)->value('name');
        }

        return [
            'companyName' => $companyName,
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
