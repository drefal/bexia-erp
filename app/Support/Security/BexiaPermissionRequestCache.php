<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BexiaPermissionRequestCache
{
    /** @var array<string, bool> */
    private static array $tableCache = [];

    /** @var array<string, bool> */
    private static array $columnCache = [];

    /** @var array<string, bool>|null */
    private static ?array $knownPermissions = null;

    /** @var array<string, array<string, bool>> */
    private static array $grantsByContext = [];

    public static function allows(mixed $user, string $ability): ?bool
    {
        $ability = trim($ability);

        if ($ability === '') {
            return null;
        }

        if (! $user || ! method_exists($user, 'getKey') || ! $user->getKey()) {
            return null;
        }

        // BEXIA_V582_PERF7G_SYSTEM_ADMIN_SHORT_CIRCUIT
        try {
            if ((bool) ($user->is_system_admin ?? false) || (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin())) {
                return true;
            }
        } catch (\Throwable) {
            // ignore
        }

        if (! self::tableExists('permissions')) {
            return null;
        }

        if (! self::isKnownPermission($ability)) {
            return null;
        }

        $companyId = self::tenantCompanyId();
        $grants = self::grantsForUser($user, $companyId);

        return isset($grants[$ability]);
    }

    private static function isKnownPermission(string $ability): bool
    {
        if (self::$knownPermissions !== null) {
            return isset(self::$knownPermissions[$ability]);
        }

        self::$knownPermissions = [];

        try {
            DB::table('permissions')
                ->where('guard_name', 'web')
                ->pluck('name')
                ->filter()
                ->each(function ($name): void {
                    self::$knownPermissions[(string) $name] = true;
                });
        } catch (\Throwable) {
            self::$knownPermissions = [];
        }

        return isset(self::$knownPermissions[$ability]);
    }

    /**
     * @return array<string, bool>
     */
    private static function grantsForUser(mixed $user, ?int $companyId): array
    {
        $userId = (int) $user->getKey();
        $modelType = get_class($user);
        $contextKey = $modelType . '|' . $userId . '|' . ($companyId ?? 'global');

        if (array_key_exists($contextKey, self::$grantsByContext)) {
            return self::$grantsByContext[$contextKey];
        }

        $grants = [];

        foreach (self::rolePermissionNames($userId, $modelType, $companyId) as $permission) {
            $permission = trim((string) $permission);

            if ($permission !== '') {
                $grants[$permission] = true;
            }
        }

        foreach (self::directPermissionNames($userId, $modelType, $companyId) as $permission) {
            $permission = trim((string) $permission);

            if ($permission !== '') {
                $grants[$permission] = true;
            }
        }

        self::$grantsByContext[$contextKey] = $grants;

        return $grants;
    }

    /**
     * @return array<int, string>
     */
    private static function rolePermissionNames(int $userId, string $modelType, ?int $companyId): array
    {
        if (
            ! self::tableExists('model_has_roles')
            || ! self::tableExists('roles')
            || ! self::tableExists('role_has_permissions')
            || ! self::tableExists('permissions')
        ) {
            return [];
        }

        try {
            $query = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'r.id')
                ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                ->where('mhr.model_id', $userId)
                ->where('p.guard_name', 'web');

            if (self::columnExists('model_has_roles', 'model_type')) {
                $query->whereIn('mhr.model_type', array_values(array_unique([
                    $modelType,
                    User::class,
                ])));
            }

            if ($companyId !== null && self::columnExists('model_has_roles', 'company_id')) {
                $query->where(function ($companyQuery) use ($companyId): void {
                    $companyQuery
                        ->where('mhr.company_id', $companyId)
                        ->orWhereNull('mhr.company_id');
                });
            }

            if ($companyId !== null && self::columnExists('roles', 'company_id')) {
                $query->where(function ($companyQuery) use ($companyId): void {
                    $companyQuery
                        ->where('r.company_id', $companyId)
                        ->orWhereNull('r.company_id');
                });
            }

            return $query
                ->distinct()
                ->pluck('p.name')
                ->map(fn ($value): string => (string) $value)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private static function directPermissionNames(int $userId, string $modelType, ?int $companyId): array
    {
        if (! self::tableExists('model_has_permissions') || ! self::tableExists('permissions')) {
            return [];
        }

        try {
            $query = DB::table('model_has_permissions as mhp')
                ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
                ->where('mhp.model_id', $userId)
                ->where('p.guard_name', 'web');

            if (self::columnExists('model_has_permissions', 'model_type')) {
                $query->whereIn('mhp.model_type', array_values(array_unique([
                    $modelType,
                    User::class,
                ])));
            }

            if ($companyId !== null && self::columnExists('model_has_permissions', 'company_id')) {
                $query->where(function ($companyQuery) use ($companyId): void {
                    $companyQuery
                        ->where('mhp.company_id', $companyId)
                        ->orWhereNull('mhp.company_id');
                });
            }

            return $query
                ->distinct()
                ->pluck('p.name')
                ->map(fn ($value): string => (string) $value)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function tenantCompanyId(): ?int
    {
        try {
            if (class_exists(\Filament\Facades\Filament::class)) {
                $tenant = \Filament\Facades\Filament::getTenant();

                if ($tenant && method_exists($tenant, 'getKey') && $tenant->getKey()) {
                    return (int) $tenant->getKey();
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            $routeTenant = request()?->route('tenant');

            if (is_numeric($routeTenant)) {
                return (int) $routeTenant;
            }

            if (is_object($routeTenant) && method_exists($routeTenant, 'getKey') && $routeTenant->getKey()) {
                return (int) $routeTenant->getKey();
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            $sessionCompanyId = session('current_company_id');

            if (is_numeric($sessionCompanyId)) {
                return (int) $sessionCompanyId;
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    public static function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableCache)) {
            return self::$tableCache[$table];
        }

        try {
            return self::$tableCache[$table] = Schema::hasTable($table);
        } catch (\Throwable) {
            return self::$tableCache[$table] = false;
        }
    }

    public static function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }

        try {
            return self::$columnCache[$key] = Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return self::$columnCache[$key] = false;
        }
    }
}
