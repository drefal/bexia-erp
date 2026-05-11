<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanyGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyGroupLimits
{
    public static function canAddCompany(?CompanyGroup $group): bool
    {
        if (! $group) return false;
        if ($group->free_trial) return true;
        if (! $group->max_companies) return true;

        return $group->companies()->count() < $group->max_companies;
    }

    public static function canAddBranch(?CompanyGroup $group): bool
    {
        if (! $group) return false;
        if ($group->free_trial) return true;
        if (! $group->max_branches) return true;

        $count = DB::table('branches')
            ->join('companies', 'branches.company_id', '=', 'companies.id')
            ->where('companies.company_group_id', $group->id)
            ->count();

        return $count < $group->max_branches;
    }

    public static function canAddUser(?CompanyGroup $group): bool
    {
        if (! $group) return false;
        if ($group->free_trial) return true;
        if (! $group->max_users) return true;

        $count = DB::table('company_user')
            ->join('companies', 'company_user.company_id', '=', 'companies.id')
            ->where('companies.company_group_id', $group->id)
            ->distinct('company_user.user_id')
            ->count('company_user.user_id');

        return $count < $group->max_users;
    }

    public static function groupFromCompany(?Company $company): ?CompanyGroup
    {
        return $company?->companyGroup;
    }

    public static function companyLimitMessage(?CompanyGroup $group): string
    {
        return "Este grupo de empresas ya alcanzó su máximo de empresas.";
    }

    public static function branchLimitMessage(?CompanyGroup $group): string
    {
        return "Este grupo de empresas ya alcanzó su máximo de sucursales.";
    }

    public static function userLimitMessage(?CompanyGroup $group): string
    {
        return "Este grupo de empresas ya alcanzó su máximo de usuarios.";
    }
}
