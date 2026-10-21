<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PayrollAttendanceReviewAuthorization
{
    public const VIEW = 'rrhh.prenomina.revisiones.ver';
    public const APPROVE = 'rrhh.prenomina.visto_bueno';
    public const MANAGE = 'rrhh.prenomina.revisiones.gestionar';

    public static function allowed(
        int $userId,
        int $companyId,
        string $permission
    ): bool {
        if (!in_array($permission, [
            self::VIEW,
            self::APPROVE,
            self::MANAGE,
        ], true)) {
            return false;
        }

        $user = User::query()->find($userId);
        $company = Company::query()->find($companyId);

        if (
            !$user
            || !$company
            || !$user->canAccessTenant($company)
        ) {
            return false;
        }

        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId) {
            return false;
        }

        $modelType = (new User())->getMorphClass();

        $direct = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_type', $modelType)
            ->where('model_id', $userId)
            ->where('company_id', $companyId)
            ->exists();

        if ($direct) {
            return true;
        }

        return DB::table('model_has_roles as mr')
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
            ->where('mr.model_type', $modelType)
            ->where('mr.model_id', $userId)
            ->where('mr.company_id', $companyId)
            ->where('r.company_id', $companyId)
            ->where('r.guard_name', 'web')
            ->where('rp.permission_id', $permissionId)
            ->exists();
    }

    public static function canReviewEmployee(
        int $userId,
        int $companyId,
        int $employeeId
    ): bool {
        if (!self::allowed(
            $userId,
            $companyId,
            self::APPROVE
        )) {
            return false;
        }

        $resolution =
            PayrollAttendanceReviewerResolver::resolve(
                $companyId,
                $employeeId
            );

        return (int) (
            $resolution['reviewer_user_id'] ?? 0
        ) === $userId;
    }
}
