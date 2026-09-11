<?php

namespace App\Support\Service;

use App\Models\ServiceCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicePickupOrderService
{
    /*
     * BEXIA_ATC_PICKUP_ORDER_SERVICE_V5_83_4C4B1
     *
     * La Orden de recoleccion existe antes de RepairOrder.
     *
     * Su informacion se almacena en:
     * service_cases.metadata.pickup_order
     */
    public function ensure(
        ServiceCase $serviceCase
    ): array {
        if (
            ! ServiceAccess::
                canEditServiceCaseMasterData()
        ) {
            throw new AuthorizationException(
                'No tienes permiso para generar la Orden de recolección.'
            );
        }

        $actorId = (int) auth()->id();

        if ($actorId <= 0) {
            throw new AuthorizationException(
                'Debes iniciar sesión.'
            );
        }

        return DB::transaction(
            function () use (
                $serviceCase,
                $actorId
            ): array {
                $case = ServiceCase::query()
                    ->whereKey(
                        $serviceCase->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertEligible(
                    $case
                );

                $metadata =
                    is_array($case->metadata)
                        ? $case->metadata
                        : [];

                $pickup =
                    (array) (
                        $metadata[
                            'pickup_order'
                        ]
                        ?? []
                    );

                $token = trim((string) (
                    $pickup['token']
                    ?? ''
                ));

                $created = false;

                /*
                 * Si ya existe token, reutilizarlo.
                 * No crear varios QR para la misma orden.
                 */
                if ($token === '') {
                    $token =
                        Str::random(64);

                    $pre =
                        (array) (
                            $metadata[
                                'repair_pre_reception'
                            ]
                            ?? []
                        );

                    $pickup = [
                        'token' =>
                            $token,

                        'status' =>
                            'pending',

                        'generated_at' =>
                            now()
                                ->toDateTimeString(),

                        'generated_by' =>
                            $actorId,

                        /*
                         * Snapshot operativo.
                         *
                         * Conserva exactamente la informacion
                         * conocida al crear la orden.
                         */
                        'reported_snapshot' => [
                            'folio' =>
                                $case->folio,

                            'created_at' =>
                                optional(
                                    $case->created_at
                                )?->toDateTimeString()
                                ?? (string)
                                    $case->created_at,

                            'priority' =>
                                $case->priority,

                            'status' =>
                                $case->status,

                            'contact_name' =>
                                $case->contact_name,

                            'contact_phone' =>
                                $case->contact_phone,

                            'contact_email' =>
                                $case->contact_email,

                            'subject' =>
                                $case->subject,

                            'description' =>
                                $case->description,

                            'channel' =>
                                $case->channel,

                            'product_name' =>
                                $case->product_name,

                            'serial_number' =>
                                $case->serial_number,

                            'lot_number' =>
                                $case->lot_number,

                            'responsible' =>
                                $this->responsibleLabel(
                                    (int) (
                                        $case
                                            ->assigned_employee_id
                                        ?? 0
                                    )
                                ),

                            'arrival_method' =>
                                $pre[
                                    'arrival_method'
                                ]
                                ?? null,

                            'arrival_method_label' =>
                                $pre[
                                    'arrival_method_label'
                                ]
                                ?? null,

                            /*
                             * En pre-recepcion este campo
                             * puede representar el punto
                             * de recoleccion previsto.
                             */
                            'pickup_place' =>
                                $pre[
                                    'reception_place'
                                ]
                                ?? null,

                            'planned_reception_at' =>
                                $pre[
                                    'planned_reception_at'
                                ]
                                ?? null,

                            'prepared_at' =>
                                $pre[
                                    'prepared_at'
                                ]
                                ?? null,

                            'attention_notes' =>
                                $case
                                    ->classification_notes,
                        ],
                    ];

                    $metadata[
                        'pickup_order'
                    ] = $pickup;

                    $case->update([
                        'metadata' =>
                            $metadata,
                    ]);

                    $now = now();

                    DB::table(
                        'service_case_events'
                    )->insert([
                        'company_id' =>
                            $case->company_id,

                        'service_case_id' =>
                            $case->id,

                        'repair_order_id' =>
                            null,

                        'event_type' =>
                            'pickup_order_created',

                        'from_status' =>
                            $case->status,

                        'to_status' =>
                            $case->status,

                        'performed_by' =>
                            $actorId,

                        'performed_at' =>
                            $now,

                        'notes' =>
                            'Orden de recolección generada.',

                        'old_values' =>
                            null,

                        'new_values' =>
                            json_encode(
                                [
                                    'pickup_order_status' =>
                                        'pending',

                                    'public_link_created' =>
                                        true,
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'metadata' =>
                            json_encode(
                                [
                                    'source' =>
                                        'service_pickup_order',
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'ip_address' =>
                            request()->ip(),

                        'user_agent' =>
                            request()->userAgent(),

                        'created_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]);

                    $created = true;
                }

                return [
                    'token' =>
                        $token,

                    'created' =>
                        $created,

                    'url' =>
                        route(
                            'public.service.pickup.show',
                            [
                                'token' =>
                                    $token,
                            ]
                        ),
                ];
            }
        );
    }

    /*
     * Resolver un ticket unicamente por token.
     *
     * El ID interno no forma parte del URL publico.
     */
    public function findByToken(
        string $token
    ): ServiceCase {
        $token = trim(
            $token
        );

        if (
            $token === ''
            || strlen($token) < 40
        ) {
            abort(404);
        }

        $case = ServiceCase::query()
            ->whereRaw(
                "(metadata::jsonb #>> '{pickup_order,token}') = ?",
                [$token]
            )
            ->first();

        if (! $case) {
            abort(404);
        }

        $metadata =
            is_array($case->metadata)
                ? $case->metadata
                : [];

        $pickup =
            (array) (
                $metadata[
                    'pickup_order'
                ]
                ?? []
            );

        if (
            in_array(
                (string) (
                    $pickup['status']
                    ?? ''
                ),
                [
                    'revoked',
                ],
                true
            )
        ) {
            abort(404);
        }

        return $case;
    }

    protected function assertEligible(
        ServiceCase $case
    ): void {
        if (
            (string) $case
                ->attention_route
                !== 'repair'
        ) {
            throw ValidationException::
                withMessages([
                    'pickup_order' =>
                        'El ticket no pertenece a la ruta de reparación.',
                ]);
        }

        if (
            (string) $case->status
                !== 'esperando_producto'
        ) {
            throw ValidationException::
                withMessages([
                    'pickup_order' =>
                        'La Orden de recolección sólo puede generarse mientras el equipo está pendiente de recepción.',
                ]);
        }

        $metadata =
            is_array($case->metadata)
                ? $case->metadata
                : [];

        $pre =
            (array) (
                $metadata[
                    'repair_pre_reception'
                ]
                ?? []
            );

        if (
            (string) (
                $pre[
                    'arrival_method'
                ]
                ?? ''
            ) !== 'recoleccion_chofer'
        ) {
            throw ValidationException::
                withMessages([
                    'pickup_order' =>
                        'Este ticket no fue preparado como Recolección por chofer.',
                ]);
        }
    }

    protected function responsibleLabel(
        int $employeeId
    ): ?string {
        if ($employeeId <= 0) {
            return null;
        }

        try {
            $options =
                ServiceAccess::
                    technicianEmployeeOptions();

            return isset(
                $options[$employeeId]
            )
                ? (string) $options[
                    $employeeId
                ]
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
