<?php

namespace App\Support\Security;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InventoryTenantScope
{
    public static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                $id = (int) $tenant->getKey();

                if ($id > 0) {
                    return $id;
                }
            }

            if (is_numeric($tenant) && (int) $tenant > 0) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
            //
        }

        $routeTenant = request()?->route('tenant');

        if (is_object($routeTenant) && method_exists($routeTenant, 'getKey')) {
            $id = (int) $routeTenant->getKey();

            if ($id > 0) {
                return $id;
            }
        }

        if (is_numeric($routeTenant) && (int) $routeTenant > 0) {
            return (int) $routeTenant;
        }

        foreach (
            [
                'current_company_id',
                'active_company_id',
                'company_id',
                'tenant_company_id',
                'filament.tenant.id',
            ] as $key
        ) {
            $id = (int) (session($key) ?? 0);

            if ($id > 0) {
                return $id;
            }
        }

        $user = auth()->user();

        foreach (['company_id', 'current_company_id', 'active_company_id'] as $field) {
            if ($user && isset($user->{$field}) && (int) $user->{$field} > 0) {
                return (int) $user->{$field};
            }
        }

        return null;
    }

    public static function currentCompanyGroupId(): ?int
    {
        $companyId = self::currentCompanyId();

        if (
            ! $companyId
            || ! Schema::hasTable('companies')
            || ! Schema::hasColumn('companies', 'company_group_id')
        ) {
            return null;
        }

        $groupId = DB::table('companies')
            ->where('id', $companyId)
            ->value('company_group_id');

        return $groupId ? (int) $groupId : null;
    }

    public static function accessibleCompanyIds(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        $companyId = self::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        $groupId = self::currentCompanyGroupId();

        if ($groupId) {
            $query = DB::table('companies')
                ->where('company_group_id', $groupId);

            if (Schema::hasColumn('companies', 'active')) {
                $query->where('active', true);
            }

            return $query
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return [$companyId];
    }

    public static function companyOptions(): array
    {
        $ids = self::accessibleCompanyIds();

        if ($ids === []) {
            return [];
        }

        return DB::table('companies')
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function isAllowedCompany(?int $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        return in_array(
            (int) $companyId,
            self::accessibleCompanyIds(),
            true
        );
    }

    public static function ensureAllowedCompanyId(?int $companyId): ?int
    {
        $ids = self::accessibleCompanyIds();

        if ($ids === []) {
            return null;
        }

        if (
            $companyId
            && in_array((int) $companyId, $ids, true)
        ) {
            return (int) $companyId;
        }

        $current = self::currentCompanyId();

        if (
            $current
            && in_array((int) $current, $ids, true)
        ) {
            return (int) $current;
        }

        return (int) $ids[0];
    }
}
