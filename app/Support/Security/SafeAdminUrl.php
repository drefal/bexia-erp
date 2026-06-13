<?php

namespace App\Support\Security;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SafeAdminUrl
{
    public static function current(): string
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if ($tenant && self::userCanUseTenant($user, (int) $tenant->getKey())) {
            return url('/admin/' . $tenant->getKey());
        }

        return self::forUser($user);
    }

    public static function forUser(?Authenticatable $user = null, ?int $preferredTenantId = null): string
    {
        return url(self::pathForUser($user, $preferredTenantId));
    }

    public static function pathForUser(?Authenticatable $user = null, ?int $preferredTenantId = null): string
    {
        if (! $user) {
            return '/admin/login';
        }

        $tenantIds = self::tenantIdsForUser($user);

        if ($preferredTenantId && in_array($preferredTenantId, $tenantIds, true)) {
            return '/admin/' . $preferredTenantId;
        }

        $sessionTenantId = (int) session('bexia_safe_admin_tenant_id', 0);

        if ($sessionTenantId && in_array($sessionTenantId, $tenantIds, true)) {
            return '/admin/' . $sessionTenantId;
        }

        if (in_array(1, $tenantIds, true)) {
            return '/admin/1';
        }

        if ($tenantIds !== []) {
            return '/admin/' . $tenantIds[0];
        }

        return '/admin/login';
    }

    public static function userCanUseTenant(?Authenticatable $user, ?int $tenantId): bool
    {
        if (! $user || ! $tenantId) {
            return false;
        }

        return in_array((int) $tenantId, self::tenantIdsForUser($user), true);
    }

    public static function tenantIdsForUser(Authenticatable $user): array
    {
        $userId = (int) $user->getAuthIdentifier();
        $ids = collect();

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            try {
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
            } catch (\Throwable) {
                //
            }
        }

        try {
            if (Schema::hasColumn('users', 'company_id') && ! empty($user->company_id)) {
                $ids->push((int) $user->company_id);
            }
        } catch (\Throwable) {
            //
        }

        try {
            if (method_exists($user, 'companies')) {
                $ids = $ids->merge(
                    $user->companies()
                        ->pluck('companies.id')
                        ->map(fn ($id) => (int) $id)
                        ->all()
                );
            }
        } catch (\Throwable) {
            //
        }

        try {
            if (Schema::hasTable('model_has_roles')) {
                $roleQuery = DB::table('model_has_roles as mhr')
                    ->where('mhr.model_id', $userId);

                if (Schema::hasColumn('model_has_roles', 'model_type')) {
                    $roleQuery->where('mhr.model_type', get_class($user));
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
        } catch (\Throwable) {
            //
        }

        foreach (['company_user', 'company_users', 'user_companies'] as $pivot) {
            try {
                if (
                    Schema::hasTable($pivot)
                    && Schema::hasColumn($pivot, 'user_id')
                    && Schema::hasColumn($pivot, 'company_id')
                ) {
                    $ids = $ids->merge(
                        DB::table($pivot)
                            ->where('user_id', $userId)
                            ->pluck('company_id')
                            ->filter()
                            ->map(fn ($id) => (int) $id)
                            ->all()
                    );
                }
            } catch (\Throwable) {
                //
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
}
