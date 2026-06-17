<?php

namespace App\Support\FiscalSat;

use App\Models\User;
use App\Support\AiInsights\AiInsightsSecurityScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FiscalSatAccess
{
    public static function can(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'can') && $user->can($permission)) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole([
            'super_admin',
            'Super Admin',
            'SuperAdmin',
            'Administrador',
            'Admin Grupo',
            'Admin Empresa',
        ])) {
            return true;
        }

        return false;
    }

    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function allowedCompanyIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if (class_exists(AiInsightsSecurityScope::class)) {
            $ids = AiInsightsSecurityScope::allowedCompanyIdsForUser($user);

            return collect($ids)
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (Schema::hasTable('companies')) {
            return DB::table('companies')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    public static function companyOptions(?User $user = null): array
    {
        $user ??= auth()->user();

        $ids = self::allowedCompanyIds($user);

        if ($ids === [] || ! Schema::hasTable('companies')) {
            return [];
        }

        return DB::table('companies')
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function scopeCompany(Builder $query): Builder
    {
        $ids = self::allowedCompanyIds(auth()->user());

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->getModel()->getTable() . '.company_id', $ids);
    }
}
