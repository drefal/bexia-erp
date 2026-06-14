<?php

namespace App\Support\AiInsights;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiInsightsSecurityScope
{
    public static function allowedCompanyIdsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $ids = collect();

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            if (Schema::hasTable('companies')) {
                return DB::table('companies')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        if (Schema::hasColumn('users', 'company_id') && ! empty($user->company_id)) {
            $ids->push((int) $user->company_id);
        }

        if (method_exists($user, 'companies')) {
            try {
                $ids = $ids->merge(
                    $user->companies()
                        ->pluck('companies.id')
                        ->map(fn ($id) => (int) $id)
                        ->all()
                );
            } catch (\Throwable) {
                //
            }
        }

        if (Schema::hasTable('model_has_roles')) {
            $roleQuery = DB::table('model_has_roles as mhr')
                ->where('mhr.model_id', $user->id);

            if (Schema::hasColumn('model_has_roles', 'model_type')) {
                $roleQuery->where('mhr.model_type', User::class);
            }

            if (Schema::hasColumn('model_has_roles', 'company_id')) {
                $ids = $ids->merge(
                    $roleQuery->clone()
                        ->pluck('mhr.company_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->all()
                );
            }

            if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'company_id')) {
                $ids = $ids->merge(
                    $roleQuery->clone()
                        ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                        ->pluck('r.company_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->all()
                );
            }
        }

        foreach (['company_user', 'company_users', 'user_companies'] as $pivot) {
            if (
                Schema::hasTable($pivot)
                && Schema::hasColumn($pivot, 'user_id')
                && Schema::hasColumn($pivot, 'company_id')
            ) {
                $ids = $ids->merge(
                    DB::table($pivot)
                        ->where('user_id', $user->id)
                        ->pluck('company_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->all()
                );
            }
        }

        return $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function canAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        return self::hasEnabledAccess($user);
    }

    public static function canManageAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin();
    }

    public static function hasEnabledAccess(User $user): bool
    {
        if (! Schema::hasTable('ai_insight_user_accesses')) {
            return false;
        }

        return DB::table('ai_insight_user_accesses')
            ->where('user_id', $user->id)
            ->where('is_enabled', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    public static function accessLevel(User $user): ?string
    {
        if (! Schema::hasTable('ai_insight_user_accesses')) {
            return null;
        }

        return DB::table('ai_insight_user_accesses')
            ->where('user_id', $user->id)
            ->where('is_enabled', true)
            ->whereNull('deleted_at')
            ->value('access_level');
    }
}
