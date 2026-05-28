<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class BexiaHrSectionHeaderWidget extends Widget
{
    protected static string $view = 'filament.widgets.bexia-dashboard-section-header-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 39;

    public static function canView(): bool
    {
        return static::bexiaDashboardWidgetVisible('hr_section_header');
    }

    protected function getViewData(): array
    {
        return [
            'sectionKey' => 'rrhh',
            'eyebrow' => 'Sección del Escritorio',
            'title' => 'Recursos Humanos',
            'description' => 'Indicadores de empleados, nómina y CFDI.',
            'accent' => '#8b5cf6',
            'softBackground' => 'linear-gradient(90deg, #f5f3ff 0%, #faf5ff 55%, #ffffff 100%)',
            'sectionBackground' => '#faf5ff',
            'sectionBorder' => '#e9d5ff',
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
