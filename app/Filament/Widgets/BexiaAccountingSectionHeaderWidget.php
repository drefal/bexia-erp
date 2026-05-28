<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class BexiaAccountingSectionHeaderWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-dashboard-section-header-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 69;

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('accounting_section_header');
    }

    protected function getViewData(): array
    {
        return [
            'sectionKey' => 'contabilidad',
            'eyebrow' => 'Sección del Escritorio',
            'title' => 'Contabilidad',
            'description' => 'Indicadores contables y procesos pendientes.',
            'accent' => '#2563eb',
            'softBackground' => 'linear-gradient(90deg, #eff6ff 0%, #dbeafe 48%, #ffffff 100%)',
            'sectionBackground' => '#eff6ff',
            'sectionBorder' => '#bfdbfe',
        ];
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
