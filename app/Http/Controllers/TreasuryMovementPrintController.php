<?php

namespace App\Http\Controllers;

use App\Models\TreasuryMovement;
use App\Support\Treasury\TreasuryMovementPdfBuilder;
use Spatie\Permission\PermissionRegistrar;

class TreasuryMovementPrintController extends Controller
{
    public function __invoke(TreasuryMovement $movement, TreasuryMovementPdfBuilder $builder)
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();
        $companyId = (int) ($movement->company_id ?? 0);

        /*
         * BEXIA_V5524B10_TREASURY_PRINT_PERMISSION_TEAM
         * Esta ruta vive fuera de /admin/{tenant}, por eso hay que fijar
         * manualmente el team/company_id de Spatie antes de evaluar permisos.
         */
        if ($companyId > 0 && class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $isAdmin = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole([
                'Super Administrador',
                'Administrador',
                'Admin',
                'admin',
                'super_admin',
                'Admin Empresa',
                'Admin Grupo',
            ])
            : false;

        $canTreasury = $user->can('treasury.view')
            || $user->can('treasury.create')
            || $user->can('treasury.update')
            || $user->can('treasury.delete');

        $sameCompany = empty($user->company_id)
            || $companyId === 0
            || (int) $user->company_id === $companyId;

        abort_unless($canTreasury && ($sameCompany || $isAdmin), 403);

        return $builder->stream($movement);
    }
}
