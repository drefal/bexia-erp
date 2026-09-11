<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Support\Service\ServicePickupOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class ServicePickupOrderLaunchController extends Controller
{
    /*
     * BEXIA_ATC_PICKUP_NEW_TAB_LAUNCH_V5_83_4C4B5
     *
     * Esta ruta NO es la Orden publica.
     *
     * Su unica funcion es:
     * 1. validar usuario
     * 2. validar firma temporal
     * 3. validar empresa/ticket
     * 4. generar/reutilizar la Orden
     * 5. redirigir a la liga publica
     */
    public function __invoke(
        Request $request,
        int $tenant,
        int $serviceCase,
        ServicePickupOrderService $service
    ): RedirectResponse {
        $actorId =
            (int) auth()->id();

        if ($actorId <= 0) {
            abort(403);
        }

        /*
         * La URL firmada queda ligada
         * al mismo usuario que hizo clic.
         */
        $signedActor =
            (int) $request->query(
                'actor',
                0
            );

        if (
            $signedActor <= 0
            || $signedActor !== $actorId
        ) {
            abort(403);
        }

        $case =
            ServiceCase::query()
                ->findOrFail(
                    $serviceCase
                );

        /*
         * Evitar abrir un ticket de otra
         * empresa cambiando parametros.
         */
        if (
            (int) $case->company_id
            !== $tenant
        ) {
            abort(404);
        }

        /*
         * Replicar el team de permisos
         * correspondiente al tenant.
         */
        app(
            PermissionRegistrar::class
        )->setPermissionsTeamId(
            $tenant
        );

        /*
         * ensure():
         * - si ya existe Orden, reutiliza token
         * - si no existe, la genera
         * - valida permisos operativos
         */
        $result =
            $service->ensure(
                $case
            );

        return redirect()->away(
            (string)
                $result['url']
        );
    }
}
