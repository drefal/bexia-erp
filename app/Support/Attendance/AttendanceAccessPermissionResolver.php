<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AttendanceAccessPermissionResolver
{
    public static function permissionsFor(
        User $actor,
        Company $company
    ): array {
        $userId = (int) $actor->getKey();
        $companyId = (int) $company->getKey();

        if (
            $userId <= 0
            || $companyId <= 0
            || !$company->active
            || !$actor->canAccessTenant($company)
        ) {
            return [];
        }

        $modelType = $actor->getMorphClass();

        $allowed =
            AttendanceAccessAuthorization::permissions();

        $direct = DB::table(
            'model_has_permissions as mp'
        )
            ->join(
                'permissions as p',
                'p.id',
                '=',
                'mp.permission_id'
            )
            ->where('mp.model_type', $modelType)
            ->where('mp.model_id', $userId)
            ->where('mp.company_id', $companyId)
            ->where('p.guard_name', 'web')
            ->whereIn('p.name', $allowed)
            ->pluck('p.name');

        $inherited = DB::table(
            'model_has_roles as mr'
        )
            ->join(
                'roles as r',
                'r.id',
                '=',
                'mr.role_id'
            )
            ->join(
                'role_has_permissions as rp',
                'rp.role_id',
                '=',
                'r.id'
            )
            ->join(
                'permissions as p',
                'p.id',
                '=',
                'rp.permission_id'
            )
            ->where('mr.model_type', $modelType)
            ->where('mr.model_id', $userId)
            ->where('mr.company_id', $companyId)
            ->where('r.company_id', $companyId)
            ->where('r.guard_name', 'web')
            ->where('p.guard_name', 'web')
            ->whereIn('p.name', $allowed)
            ->pluck('p.name');

        return $direct
            ->merge($inherited)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function hasPermission(
        User $actor,
        Company $company,
        string $permission
    ): bool {
        return in_array(
            $permission,
            self::permissionsFor($actor, $company),
            true
        );
    }
}
