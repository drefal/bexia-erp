<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserDashboardPreferenceService
{
    public function __construct(
        protected DashboardWidgetRegistry $registry
    ) {
    }

    public function syncUserDefaults(int $companyId, int $userId, ?int $actorUserId = null): Collection
    {
        if (
            $companyId <= 0
            || $userId <= 0
            || ! Schema::hasTable('dashboard_widget_user_settings')
        ) {
            return collect();
        }

        foreach ($this->registry->catalog() as $key => $definition) {
            $exists = DB::table('dashboard_widget_user_settings')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->where('widget_key', (string) $key)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->upsertPreference(
                companyId: $companyId,
                userId: $userId,
                widgetKey: (string) $key,
                isVisible: (bool) ($definition['default_visible'] ?? true),
                sortOrder: (int) ($definition['sort_order'] ?? 100),
                settings: [],
                actorUserId: $actorUserId,
            );
        }

        return $this->registry->preferencesFor($companyId, $userId);
    }

    public function setVisibility(int $companyId, int $userId, string $widgetKey, bool $isVisible, ?int $actorUserId = null): void
    {
        if (
            $companyId <= 0
            || $userId <= 0
            || $widgetKey === ''
            || ! Schema::hasTable('dashboard_widget_user_settings')
        ) {
            return;
        }

        $definition = $this->registry->catalog()[$widgetKey] ?? [];

        $this->upsertPreference(
            companyId: $companyId,
            userId: $userId,
            widgetKey: $widgetKey,
            isVisible: $isVisible,
            sortOrder: (int) ($definition['sort_order'] ?? 100),
            settings: [],
            actorUserId: $actorUserId,
        );
    }

    public function setOrder(int $companyId, int $userId, array $orderedWidgetKeys, ?int $actorUserId = null): void
    {
        if (
            $companyId <= 0
            || $userId <= 0
            || ! Schema::hasTable('dashboard_widget_user_settings')
        ) {
            return;
        }

        foreach (array_values($orderedWidgetKeys) as $index => $widgetKey) {
            DB::table('dashboard_widget_user_settings')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->where('widget_key', (string) $widgetKey)
                ->update([
                    'sort_order' => ($index + 1) * 10,
                    'updated_by_user_id' => $actorUserId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function resetUser(int $companyId, int $userId): void
    {
        if (
            $companyId <= 0
            || $userId <= 0
            || ! Schema::hasTable('dashboard_widget_user_settings')
        ) {
            return;
        }

        DB::table('dashboard_widget_user_settings')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->delete();
    }

    protected function upsertPreference(
        int $companyId,
        int $userId,
        string $widgetKey,
        bool $isVisible,
        int $sortOrder,
        array $settings = [],
        ?int $actorUserId = null
    ): void {
        $existing = DB::table('dashboard_widget_user_settings')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('widget_key', $widgetKey)
            ->first();

        if ($existing) {
            DB::table('dashboard_widget_user_settings')
                ->where('id', $existing->id)
                ->update([
                    'is_visible' => $isVisible,
                    'sort_order' => $sortOrder,
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_by_user_id' => $actorUserId,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('dashboard_widget_user_settings')->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'widget_key' => $widgetKey,
            'is_visible' => $isVisible,
            'sort_order' => $sortOrder,
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => $actorUserId,
            'updated_by_user_id' => $actorUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
