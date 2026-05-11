<?php

namespace App\Support;

use App\Models\Company;

class CompanyLimits
{
    public static function isUnlimited(?Company $company): bool
    {
        return (bool) ($company?->free_trial ?? false);
    }

    public static function canAddUser(?Company $company, int $increment = 1): bool
    {
        if (! $company) {
            return true;
        }

        if (self::isUnlimited($company)) {
            return true;
        }

        $limit = (int) ($company->max_users ?? 0);

        if ($limit <= 0) {
            return true;
        }

        $current = (int) $company->users()->count();

        return ($current + $increment) <= $limit;
    }

    public static function userLimitMessage(?Company $company): string
    {
        $limit = (int) ($company?->max_users ?? 0);

        return $limit > 0
            ? "Esta empresa ya alcanzó su máximo de usuarios ({$limit}). Activa Sin Restricciones o aumenta el límite."
            : 'Esta empresa ya alcanzó su máximo de usuarios.';
    }

    public static function canAddBranch(?Company $company, int $currentBranches, int $increment = 1): bool
    {
        if (! $company) {
            return true;
        }

        if (self::isUnlimited($company)) {
            return true;
        }

        $limit = (int) ($company->max_branches ?? 0);

        if ($limit <= 0) {
            return true;
        }

        return ($currentBranches + $increment) <= $limit;
    }

    public static function branchLimitMessage(?Company $company): string
    {
        $limit = (int) ($company?->max_branches ?? 0);

        return $limit > 0
            ? "Esta empresa ya alcanzó su máximo de sucursales ({$limit}). Activa Sin Restricciones o aumenta el límite."
            : 'Esta empresa ya alcanzó su máximo de sucursales.';
    }
}
