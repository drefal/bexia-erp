<?php

namespace App\Support;

use App\Support\Security\BexiaTenantPermission;
use Filament\Facades\Filament;

class CustomBalloonsAccess
{
    public const COMPANY_ID = 3;

    public static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $routeTenant = request()?->route('tenant');

        if ($routeTenant instanceof \Illuminate\Database\Eloquent\Model) {
            return (int) $routeTenant->getKey();
        }

        return is_numeric($routeTenant)
            ? (int) $routeTenant
            : null;
    }

    public static function isPapelon(): bool
    {
        return self::currentCompanyId() === self::COMPANY_ID;
    }

    public static function can(string $permission): bool
    {
        if (! self::isPapelon()) {
            return false;
        }

        return BexiaTenantPermission::can($permission);
    }

    public static function any(array $permissions): bool
    {
        if (! self::isPapelon()) {
            return false;
        }

        return BexiaTenantPermission::any($permissions);
    }

    public static function canViewCatalog(): bool
    {
        return self::any([
            'custom_balloons.catalog.view',
            'custom_balloons.catalog.manage',
        ]);
    }

    public static function canManageCatalog(): bool
    {
        return self::can('custom_balloons.catalog.manage');
    }

    public static function canManagePrices(): bool
    {
        return self::can('custom_balloons.prices.manage');
    }

    public static function canManageExtras(): bool
    {
        return self::can('custom_balloons.extras.manage');
    }
}
