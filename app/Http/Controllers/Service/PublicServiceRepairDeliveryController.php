<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Support\Service\ServiceRepairPublicDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * BEXIA_ATC_PUBLIC_REPAIR_DELIVERY_CONTROLLER_V5_83_4C5G13A
 *
 * NO requiere login.
 *
 * GET:
 * consulta segura por token.
 *
 * POST:
 * captura evidencia/firma y delega la
 * entrega a ServiceRepairDeliveryService.
 */
class PublicServiceRepairDeliveryController extends Controller
{
    public function show(
        string $token,
        ServiceRepairPublicDeliveryService $service
    ) {
        $repair =
            $service->resolve(
                $token
            );

        $delivery =
            $service->payload(
                $repair
            );

        return view(
            'service.repair-delivery-public',
            [
                'token' =>
                    $token,

                'delivery' =>
                    $delivery,
            ]
        );
    }

    public function store(
        Request $request,
        string $token,
        ServiceRepairPublicDeliveryService $service
    ) {
        $repair =
            $service->resolve(
                $token
            );

        $payload =
            $service->payload(
                $repair
            );

        if (
            ! (
                $payload[
                    'can_submit'
                ]
                ?? false
            )
        ) {
            throw
                ValidationException::
                    withMessages([
                        'delivery' =>
                            $payload[
                                'blocked_reason'
                            ]
                            ?? 'Esta entrega ya no está disponible.',
                    ]);
        }

        $validated =
            $request->validate([
                'driver_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'delivered_to' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'delivery_notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'latitude' => [
                    'nullable',
                    'numeric',
                    'between:-90,90',
                ],

                'longitude' => [
                    'nullable',
                    'numeric',
                    'between:-180,180',
                ],

                'delivery_files' => [
                    'required',
                    'array',
                    'min:1',
                    'max:10',
                ],

                'delivery_files.*' => [
                    'required',
                    'file',
                    'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                    'max:10240',
                ],

                'delivery_signature_data' => [
                    'required',
                    'string',
                ],
            ], [
                'driver_name.required' =>
                    'Captura el nombre del chofer.',

                'delivered_to.required' =>
                    'Captura el nombre de quien recibe.',

                'delivery_files.required' =>
                    'Agrega al menos una evidencia de entrega.',

                'delivery_files.min' =>
                    'Agrega al menos una evidencia de entrega.',

                'delivery_signature_data.required' =>
                    'Solicita la firma de quien recibe.',
            ]);

        $storedPaths = [];

        try {
            foreach (
                $request->file(
                    'delivery_files',
                    []
                )
                as $file
            ) {
                $extension =
                    strtolower(
                        (string) (
                            $file->
                                guessExtension()
                            ?: $file->
                                getClientOriginalExtension()
                            ?: 'bin'
                        )
                    );

                $extension =
                    preg_replace(
                        '/[^a-z0-9]/',
                        '',
                        $extension
                    );

                if ($extension === '') {
                    $extension = 'bin';
                }

                $filename =
                    (string) Str::ulid()
                    . '.'
                    . $extension;

                $path =
                    $file->storeAs(
                        'service/delivery-files/'
                            . (int)
                                $repair->
                                    getKey(),
                        $filename,
                        'public'
                    );

                if (
                    ! is_string($path)
                    || $path === ''
                ) {
                    throw
                        ValidationException::
                            withMessages([
                                'delivery_files' =>
                                    'No fue posible guardar una evidencia.',
                            ]);
                }

                $storedPaths[] =
                    $path;
            }

            $validated[
                'delivery_files'
            ] = $storedPaths;

            $service->deliver(
                token:
                    $token,

                data:
                    $validated,

                ipAddress:
                    $request->ip(),

                userAgent:
                    $request->
                        userAgent()
            );
        } catch (\Throwable $e) {
            /*
             * ServiceRepairDeliveryService ya
             * limpia sus archivos no referenciados.
             * Este bloque cubre errores ocurridos
             * antes de entrar al service atómico.
             */
            foreach (
                $storedPaths
                as $path
            ) {
                try {
                    if (
                        Storage::
                            disk('public')
                            ->exists(
                                $path
                            )
                    ) {
                        Storage::
                            disk('public')
                            ->delete(
                                $path
                            );
                    }
                } catch (\Throwable) {
                }
            }

            throw $e;
        }

        return redirect()
            ->route(
                'public.service.repair-delivery.show',
                [
                    'token' =>
                        $token,
                ]
            )
            ->with(
                'delivery_success',
                true
            );
    }
}
