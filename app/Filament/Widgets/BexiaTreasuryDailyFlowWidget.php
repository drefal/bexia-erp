<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaTreasuryDailyFlowWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-treasury-daily-flow-widget';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 83;

    // BEXIA_V5727N29F_TREASURY_POLLING_10MIN_NO_SPATIE_INFO
    protected static ?string $pollingInterval = '600s';

    public static function canView(): bool
    {
        // BEXIA_V57210G2_DASHBOARD_WIDGET_PERMISSION
        if (! \App\Support\Security\BexiaAccess::dashboard()) {
            return false;
        }

        return static::bexiaDashboardWidgetVisible('treasury_daily_flow');
    }

    protected function getViewData(): array
    {
        $companyId = app(DashboardWidgetRegistry::class)->currentCompanyId();
        $hours = collect(range(0, 23))->mapWithKeys(fn (int $hour): array => [
            $hour => [
                'hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                'in' => 0.0,
                'out' => 0.0,
            ],
        ]);

        if ($companyId && Schema::hasTable('treasury_movements')) {
            DB::table('treasury_movements')
                ->where('company_id', $companyId)
                ->whereDate('created_at', now()->toDateString())
                ->selectRaw("extract(hour from created_at)::int as hour, type, coalesce(sum(amount), 0) as total")
                ->groupBy(DB::raw("extract(hour from created_at)::int"), 'type')
                ->orderBy(DB::raw("extract(hour from created_at)::int"))
                ->get()
                ->each(function ($row) use (&$hours): void {
                    $hour = (int) $row->hour;
                    $type = (string) $row->type;
                    $amount = (float) $row->total;

                    if (! $hours->has($hour)) {
                        return;
                    }

                    $data = $hours->get($hour);

                    if (in_array($type, ['inflow', 'inbound'], true)) {
                        $data['in'] += $amount;
                    }

                    if (in_array($type, ['outflow', 'outbound'], true)) {
                        $data['out'] += $amount;
                    }

                    $hours->put($hour, $data);
                });
        }

        $series = $hours->values();
        $max = max(1.0, (float) $series->max(fn (array $row): float => max($row['in'], $row['out'])));

        return [
            'series' => $series,
            'max' => $max,
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
