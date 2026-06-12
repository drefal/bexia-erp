<?php

namespace App\Support\Security;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BexiaAccess
{
    public static function can(string $permission): bool
    {
        return static::any([$permission]);
    }

    public static function any(array $permissions): bool
    {
        $permissions = array_values(array_filter(array_unique(array_map(
            fn ($permission) => trim((string) $permission),
            $permissions
        ))));

        if ($permissions === []) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            try {
                if (method_exists($user, 'can') && $user->can($permission)) {
                    return true;
                }
            } catch (\Throwable) {
                //
            }
        }

        return static::directDatabasePermissionCheck($user, $permissions);
    }

    public static function dashboard(): bool
    {
        return static::can('dashboard.ver');
    }

    public static function salidas(): bool
    {
        return static::any([
            'salidas.ver',
            'salidas.view',
            'salidas.access',
            'salidas.ver_todas',
            'salidas.configurar',
        ]);
    }

    public static function salidasConfigurar(): bool
    {
        return static::can('salidas.configurar');
    }

    public static function inventory(): bool
    {
        return static::can('inventory.menu.view');
    }

    public static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if ($tenant && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }
        } catch (\Throwable) {
            //
        }

        try {
            $routeTenant = request()?->route('tenant');

            if (is_object($routeTenant) && method_exists($routeTenant, 'getKey')) {
                return (int) $routeTenant->getKey();
            }

            if (is_numeric($routeTenant)) {
                return (int) $routeTenant;
            }
        } catch (\Throwable) {
            //
        }

        try {
            $segments = request()?->segments() ?? [];

            foreach ($segments as $index => $segment) {
                if ($segment === 'admin' && isset($segments[$index + 1]) && is_numeric($segments[$index + 1])) {
                    return (int) $segments[$index + 1];
                }
            }
        } catch (\Throwable) {
            //
        }

        $user = auth()->user();

        if ($user && isset($user->company_id) && is_numeric($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

    protected static function directDatabasePermissionCheck(object $user, array $permissions): bool
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_has_permissions')
            || ! Schema::hasTable('model_has_roles')
        ) {
            return false;
        }

        $userId = method_exists($user, 'getAuthIdentifier')
            ? (int) $user->getAuthIdentifier()
            : (int) ($user->id ?? 0);

        if ($userId <= 0) {
            return false;
        }

        $modelType = get_class($user);
        $companyId = static::currentCompanyId();

        $query = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
            ->where('mhr.model_id', $userId)
            ->whereIn('p.name', $permissions);

        if (Schema::hasColumn('model_has_roles', 'model_type')) {
            $query->where('mhr.model_type', $modelType);
        }

        if ($companyId !== null && Schema::hasColumn('model_has_roles', 'company_id')) {
            $query->where(function ($companyQuery) use ($companyId): void {
                $companyQuery
                    ->where('mhr.company_id', $companyId)
                    ->orWhereNull('mhr.company_id');
            });
        }

        if ($companyId !== null && Schema::hasColumn('roles', 'company_id')) {
            $query->where(function ($companyQuery) use ($companyId): void {
                $companyQuery
                    ->where('r.company_id', $companyId)
                    ->orWhereNull('r.company_id');
            });
        }

        if ($query->exists()) {
            return true;
        }

        if (! Schema::hasTable('model_has_permissions')) {
            return false;
        }

        $direct = DB::table('model_has_permissions as mhp')
            ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
            ->where('mhp.model_id', $userId)
            ->whereIn('p.name', $permissions);

        if (Schema::hasColumn('model_has_permissions', 'model_type')) {
            $direct->where('mhp.model_type', $modelType);
        }

        if ($companyId !== null && Schema::hasColumn('model_has_permissions', 'company_id')) {
            $direct->where(function ($companyQuery) use ($companyId): void {
                $companyQuery
                    ->where('mhp.company_id', $companyId)
                    ->orWhereNull('mhp.company_id');
            });
        }

        return $direct->exists();
    }
}
