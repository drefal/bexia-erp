<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

final class PayrollGroupAuthorization
{
    public const VIEW = 'nomina.grupos.ver';
    public const CREATE = 'nomina.grupos.crear';
    public const EDIT = 'nomina.grupos.editar';
    public const DELETE = 'nomina.grupos.eliminar';

    public static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            $id = (int) $tenant->getKey();

            return $id > 0 ? $id : null;
        }

        $routeTenant = request()->route('tenant');

        if (is_object($routeTenant)
            && method_exists($routeTenant, 'getKey')) {
            $routeTenant = $routeTenant->getKey();
        }

        if (is_numeric($routeTenant)) {
            $id = (int) $routeTenant;

            return $id > 0 ? $id : null;
        }

        return null;
    }

    public static function currentUserCan(string $permission): bool
    {
        $userId = (int) (auth()->id() ?? 0);
        $companyId = self::currentCompanyId();

        return $userId > 0
            && $companyId !== null
            && self::allowed($userId, $companyId, $permission);
    }

    public static function allowed(
        int $userId,
        int $companyId,
        string $permission
    ): bool {
        if (!in_array($permission, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
        ], true)) {
            return false;
        }

        if ($userId <= 0 || $companyId <= 0) {
            return false;
        }

        $user = User::query()->find($userId);
        $company = Company::query()->find($companyId);

        if (!$user || !$company || !$user->canAccessTenant($company)) {
            return false;
        }

        // Conserva el acceso administrativo de sistema,
        // pero siempre exige acceso al tenant solicitado.
        if ((bool) ($user->is_system_admin ?? false)
            || ($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId) {
            return false;
        }

        $modelType = (new User())->getMorphClass();

        if (DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_type', $modelType)
            ->where('model_id', $userId)
            ->where('company_id', $companyId)
            ->exists()) {
            return true;
        }

        return DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('role_has_permissions as rp',
                'rp.role_id', '=', 'r.id')
            ->where('mr.model_type', $modelType)
            ->where('mr.model_id', $userId)
            ->where('mr.company_id', $companyId)
            ->where('r.company_id', $companyId)
            ->where('r.guard_name', 'web')
            ->where('rp.permission_id', $permissionId)
            ->exists();
    }
}
