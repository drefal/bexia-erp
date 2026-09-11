<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\RepairOrder;
use App\Support\Service\ServiceAccess;
use App\Support\Service\ServiceRepairPublicDeliveryService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

/*
 * BEXIA_ATC_PUBLIC_REPAIR_DELIVERY_LAUNCH_V5_83_4C5G13A
 *
 * Esta ruta SI requiere usuario Bexia.
 *
 * Su única función es:
 * - validar actor
 * - validar empresa
 * - validar reparación
 * - validar autorización de entrega
 * - generar/reutilizar token
 * - redirigir a página pública
 */
class ServiceRepairDeliveryLaunchController extends Controller
{
    public function __invoke(
        Request $request,
        int|string $tenant,
        int|string $repairOrder
    ) {
        abort_unless(
            auth()->check(),
            403
        );

        abort_unless(
            (int) $request->query(
                'actor'
            ) ===
                (int) auth()->id(),
            403
        );

        $company =
            Company::query()
                ->findOrFail(
                    (int) $tenant
                );

        $repair =
            RepairOrder::query()
                ->whereKey(
                    (int) $repairOrder
                )
                ->where(
                    'company_id',
                    (int) $tenant
                )
                ->firstOrFail();

        /*
         * Mismo contexto multiempresa
         * utilizado por el launcher
         * de recolección.
         */
        app(
            PermissionRegistrar::class
        )->setPermissionsTeamId(
            (int) $tenant
        );

        try {
            Filament::setTenant(
                $company,
                true
            );
        } catch (\Throwable) {
            /*
             * PermissionRegistrar ya quedó
             * ligado al tenant. ServiceAccess
             * vuelve a validar el RepairOrder.
             */
        }

        abort_unless(
            ServiceAccess::
                canDeliverRepair(
                    $repair
                ),
            403
        );

        $prepared =
            app(
                ServiceRepairPublicDeliveryService::class
            )->prepare(
                $repair
            );

        return redirect()->route(
            'public.service.repair-delivery.show',
            [
                'token' =>
                    $prepared[
                        'token'
                    ],
            ]
        );
    }
}
