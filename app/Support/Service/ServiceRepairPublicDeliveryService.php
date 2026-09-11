<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * BEXIA_ATC_PUBLIC_REPAIR_DELIVERY_SERVICE_V5_83_4C5G13A
 *
 * Liga publica para entrega de equipo ATC.
 *
 * NO registra pagos.
 * NO crea movimientos de inventario.
 * NO duplica la entrega.
 *
 * La autoridad final sigue siendo:
 * ServiceRepairDeliveryService.
 */
class ServiceRepairPublicDeliveryService
{
    public const TOKEN_TTL_HOURS = 48;

    public function prepare(
        RepairOrder $repairOrder
    ): array {
        if (! auth()->check()) {
            throw new AuthorizationException(
                'Debes iniciar sesión para preparar la entrega.'
            );
        }

        if (
            ! ServiceAccess::canDeliverRepair(
                $repairOrder
            )
        ) {
            throw new AuthorizationException(
                'La reparación no está autorizada para entrega.'
            );
        }

        $repairId =
            (int) $repairOrder->getKey();

        return DB::transaction(
            function () use ($repairId): array {
                $repair =
                    RepairOrder::query()
                        ->whereKey($repairId)
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Revalidar después del lock.
                 */
                if (
                    ! ServiceAccess::
                        canDeliverRepair(
                            $repair
                        )
                ) {
                    throw new
                        AuthorizationException(
                            'La reparación ya no está autorizada para entrega.'
                        );
                }

                $metadata =
                    $this->metadataArray(
                        $repair->metadata
                            ?? []
                    );

                $current =
                    data_get(
                        $metadata,
                        'public_delivery'
                    );

                if (is_array($current)) {
                    $status =
                        (string) (
                            $current['status']
                            ?? ''
                        );

                    $expiresAt =
                        (string) (
                            $current['expires_at']
                            ?? ''
                        );

                    $encrypted =
                        (string) (
                            $current[
                                'token_encrypted'
                            ]
                            ?? ''
                        );

                    if (
                        $status === 'pending'
                        && ! $this->
                            isExpired(
                                $expiresAt
                            )
                        && $encrypted !== ''
                    ) {
                        try {
                            $existingToken =
                                Crypt::
                                    decryptString(
                                        $encrypted
                                    );

                            $existingHash =
                                (string) (
                                    $current[
                                        'token_sha256'
                                    ]
                                    ?? ''
                                );

                            if (
                                preg_match(
                                    '/^[A-Za-z0-9]{64}$/D',
                                    $existingToken
                                )
                                && hash_equals(
                                    $existingHash,
                                    hash(
                                        'sha256',
                                        $existingToken
                                    )
                                )
                            ) {
                                return [
                                    'repair' =>
                                        $repair,

                                    'token' =>
                                        $existingToken,

                                    'reused' =>
                                        true,
                                ];
                            }
                        } catch (\Throwable) {
                            /*
                             * Token viejo/cifrado invalido:
                             * se regenera de manera segura.
                             */
                        }
                    }
                }

                $token =
                    Str::random(64);

                $tokenHash =
                    hash(
                        'sha256',
                        $token
                    );

                $now = now();

                $expiresAt =
                    $now
                        ->copy()
                        ->addHours(
                            self::
                                TOKEN_TTL_HOURS
                        );

                $metadata[
                    'public_delivery'
                ] = [
                    'status' =>
                        'pending',

                    'token_sha256' =>
                        $tokenHash,

                    /*
                     * Sólo se conserva cifrado para que
                     * el usuario interno pueda volver
                     * a abrir/copiar LA MISMA liga.
                     */
                    'token_encrypted' =>
                        Crypt::
                            encryptString(
                                $token
                            ),

                    'generated_at' =>
                        $now->
                            toDateTimeString(),

                    'expires_at' =>
                        $expiresAt->
                            toDateTimeString(),

                    'generated_by_user_id' =>
                        auth()->id(),

                    'used_at' =>
                        null,

                    'source' =>
                        'public_repair_delivery',
                ];

                $repair
                    ->forceFill([
                        'metadata' =>
                            $metadata,
                    ])
                    ->save();

                $this->logEvent(
                    repair:
                        $repair,

                    eventType:
                        'repair_public_delivery_link_created',

                    fromStatus:
                        (string) (
                            $repair->
                                workflow_stage
                            ?? ''
                        ),

                    toStatus:
                        (string) (
                            $repair->
                                workflow_stage
                            ?? ''
                        ),

                    actorId:
                        auth()->id(),

                    notes:
                        'Se generó una liga segura para la entrega pública del equipo.',

                    metadata: [
                        'source' =>
                            'public_repair_delivery',

                        'token_sha256' =>
                            $tokenHash,

                        'expires_at' =>
                            $expiresAt->
                                toDateTimeString(),
                    ],

                    ipAddress:
                        request()->ip(),

                    userAgent:
                        request()->
                            userAgent()
                );

                return [
                    'repair' =>
                        $repair->fresh(),

                    'token' =>
                        $token,

                    'reused' =>
                        false,
                ];
            }
        );
    }

    public function resolve(
        string $token
    ): RepairOrder {
        $token =
            trim($token);

        if (
            ! preg_match(
                '/^[A-Za-z0-9]{64}$/D',
                $token
            )
        ) {
            throw (
                new ModelNotFoundException()
            )->setModel(
                RepairOrder::class
            );
        }

        $hash =
            hash(
                'sha256',
                $token
            );

        $repair = null;

        /*
         * PostgreSQL JSON/JSONB.
         */
        try {
            $repair =
                RepairOrder::query()
                    ->where(
                        'metadata->public_delivery->token_sha256',
                        $hash
                    )
                    ->first();
        } catch (\Throwable) {
            $repair = null;
        }

        /*
         * Fallback defensivo por si metadata
         * no está expuesta como JSON nativo.
         */
        if (! $repair) {
            $candidates =
                RepairOrder::query()
                    ->whereNotNull(
                        'metadata'
                    )
                    ->orderByDesc('id')
                    ->limit(5000)
                    ->get();

            foreach ($candidates as $candidate) {
                $candidateHash =
                    (string) data_get(
                        $this->metadataArray(
                            $candidate->
                                metadata
                            ?? []
                        ),
                        'public_delivery.token_sha256',
                        ''
                    );

                if (
                    $candidateHash !== ''
                    && hash_equals(
                        $candidateHash,
                        $hash
                    )
                ) {
                    $repair =
                        $candidate;

                    break;
                }
            }
        }

        if (! $repair) {
            throw (
                new ModelNotFoundException()
            )->setModel(
                RepairOrder::class
            );
        }

        $storedHash =
            (string) data_get(
                $this->metadataArray(
                    $repair->metadata
                        ?? []
                ),
                'public_delivery.token_sha256',
                ''
            );

        if (
            $storedHash === ''
            || ! hash_equals(
                $storedHash,
                $hash
            )
        ) {
            throw (
                new ModelNotFoundException()
            )->setModel(
                RepairOrder::class
            );
        }

        return $repair;
    }

    public function payload(
        RepairOrder $repair
    ): array {
        $metadata =
            $this->metadataArray(
                $repair->metadata
                    ?? []
            );

        $public =
            data_get(
                $metadata,
                'public_delivery'
            );

        $public =
            is_array($public)
                ? $public
                : [];

        $publicStatus =
            (string) (
                $public['status']
                ?? ''
            );

        $expiresAt =
            (string) (
                $public['expires_at']
                ?? ''
            );

        $completed =
            $publicStatus === 'completed'
            || filled(
                $repair->delivered_at
            )
            || in_array(
                (string) (
                    $repair->
                        workflow_stage
                    ?? ''
                ),
                [
                    'delivered',
                    'entregado',
                ],
                true
            );

        $expired =
            ! $completed
            && $this->isExpired(
                $expiresAt
            );

        $bridge =
            data_get(
                $metadata,
                'post_repair_collection_delivery'
            );

        $bridge =
            is_array($bridge)
                ? $bridge
                : [];

        $policy =
            trim(
                (string) (
                    $bridge[
                        'collection_policy'
                    ]
                    ?? ''
                )
            );

        /*
         * Compatibilidad con flujo sin cobro.
         */
        if (
            $policy === ''
            && data_get(
                $metadata,
                'post_repair_no_charge_delivery_bridge'
            )
        ) {
            $policy =
                'no_charge';
        }

        $case = null;

        if (
            filled(
                $repair->
                    service_case_id
            )
            && Schema::hasTable(
                'service_cases'
            )
        ) {
            $case =
                DB::table(
                    'service_cases'
                )
                    ->where(
                        'id',
                        $repair->
                            service_case_id
                    )
                    ->where(
                        'company_id',
                        $repair->
                            company_id
                    )
                    ->first();
        }

        $receivable = null;

        $receivableId =
            (int) (
                $repair->
                    account_receivable_id
                ?? 0
            );

        if (
            $receivableId > 0
            && Schema::hasTable(
                'account_receivables'
            )
        ) {
            $columns =
                Schema::
                    getColumnListing(
                        'account_receivables'
                    );

            $query =
                DB::table(
                    'account_receivables'
                )
                    ->where(
                        'id',
                        $receivableId
                    );

            if (
                in_array(
                    'company_id',
                    $columns,
                    true
                )
            ) {
                $query->where(
                    'company_id',
                    $repair->
                        company_id
                );
            }

            if (
                in_array(
                    'source_type',
                    $columns,
                    true
                )
            ) {
                $query->where(
                    'source_type',
                    'service_repair_order'
                );
            }

            if (
                in_array(
                    'source_id',
                    $columns,
                    true
                )
            ) {
                $query->where(
                    'source_id',
                    $repair->
                        getKey()
                );
            }

            $receivable =
                $query->first();
        }

        $document =
            ServiceAccess::
                repairExitDocument(
                    $repair
                );

        $document =
            is_array($document)
                ? $document
                : [];

        $balance =
            $receivable
                ? (float) (
                    $receivable->
                        balance_total
                    ?? 0
                )
                : (float) (
                    $bridge[
                        'final_amount'
                    ]
                    ?? $bridge[
                        'authorized_total'
                    ]
                    ?? 0
                );

        $amountToCollect = null;

        if (
            $policy ===
                'payment_on_delivery'
        ) {
            $amountToCollect =
                max(
                    0,
                    round(
                        $balance,
                        2
                    )
                );
        }

        $conditionLabel =
            match ($policy) {
                'payment_on_delivery' =>
                    'Cobro contra entrega',

                'credit_allowed' =>
                    'Crédito autorizado · no requiere cobro previo para entregar',

                'payment_before_delivery' =>
                    'Pago previo validado',

                'payment_required' =>
                    'Pago previo validado',

                'no_charge' =>
                    'Sin cobro',

                default =>
                    'Entrega autorizada',
            };

        /*
         * El actor público NO tiene permiso/rol.
         * La excepción de actor vive sólo dentro
         * de este contexto efímero de servidor.
         *
         * TODOS los demás gates siguen vigentes.
         */
        $serverGate =
            ServiceAccess::
                withVerifiedPublicRepairDeliveryContext(
                    fn (): bool =>
                        ServiceAccess::
                            canDeliverRepair(
                                $repair
                            )
                );

        $canSubmit =
            $publicStatus === 'pending'
            && ! $expired
            && ! $completed
            && $serverGate;

        $blockedReason = null;

        if ($completed) {
            $blockedReason =
                'Esta entrega ya fue registrada.';
        } elseif ($expired) {
            $blockedReason =
                'La liga de entrega venció. Solicita una nueva liga al área de servicio.';
        } elseif (
            $publicStatus !== 'pending'
        ) {
            $blockedReason =
                'La liga de entrega no está activa.';
        } elseif (! $serverGate) {
            $blockedReason =
                'La reparación ya no cumple las condiciones para ser entregada.';
        }

        $customerName =
            trim(
                (string) (
                    $case->customer_name
                    ?? $case->contact_name
                    ?? $case->customer
                    ?? ''
                )
            );

        $productName =
            trim(
                (string) (
                    $case->product_name
                    ?? $repair->product_name
                    ?? ''
                )
            );

        $serialNumber =
            trim(
                (string) (
                    $case->serial_number
                    ?? $repair->serial_number
                    ?? ''
                )
            );

        return [
            'repair_id' =>
                (int) $repair->getKey(),

            'repair_folio' =>
                (string) (
                    $repair->folio
                    ?? ''
                ),

            'service_case_id' =>
                (int) (
                    $repair->
                        service_case_id
                    ?? 0
                ),

            'service_case_folio' =>
                (string) (
                    $case->folio
                    ?? ''
                ),

            'company_id' =>
                (int) (
                    $repair->
                        company_id
                    ?? 0
                ),

            'customer_name' =>
                $customerName,

            'product_name' =>
                $productName,

            'serial_number' =>
                $serialNumber,

            'sal_folio' =>
                (string) (
                    $document[
                        'folio'
                    ]
                    ?? ''
                ),

            'policy' =>
                $policy,

            'condition_label' =>
                $conditionLabel,

            'amount_to_collect' =>
                $amountToCollect,

            'currency' =>
                (string) (
                    $receivable->currency
                    ?? $repair->currency
                    ?? 'MXN'
                ),

            'public_status' =>
                $publicStatus,

            'expires_at' =>
                $expiresAt,

            'generated_at' =>
                (string) (
                    $public[
                        'generated_at'
                    ]
                    ?? ''
                ),

            'completed' =>
                $completed,

            'expired' =>
                $expired,

            'can_submit' =>
                $canSubmit,

            'blocked_reason' =>
                $blockedReason,

            'driver_name' =>
                (string) (
                    $public[
                        'driver_name'
                    ]
                    ?? ''
                ),

            'delivered_to' =>
                (string) (
                    $repair->
                        delivered_to
                    ?? $public[
                        'delivered_to'
                    ]
                    ?? ''
                ),

            'delivered_at' =>
                $repair->delivered_at
                    ? (string)
                        $repair->
                            delivered_at
                    : (
                        (string) (
                            $public[
                                'used_at'
                            ]
                            ?? ''
                        )
                    ),
        ];
    }

    public function deliver(
        string $token,
        array $data,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): RepairOrder {
        $resolved =
            $this->resolve(
                $token
            );

        $repairId =
            (int)
                $resolved->getKey();

        $tokenHash =
            hash(
                'sha256',
                trim($token)
            );

        $driverName =
            trim(
                (string) (
                    $data[
                        'driver_name'
                    ]
                    ?? ''
                )
            );

        $deliveredTo =
            trim(
                (string) (
                    $data[
                        'delivered_to'
                    ]
                    ?? ''
                )
            );

        if ($driverName === '') {
            throw
                ValidationException::
                    withMessages([
                        'driver_name' =>
                            'Captura el nombre del chofer.',
                    ]);
        }

        if ($deliveredTo === '') {
            throw
                ValidationException::
                    withMessages([
                        'delivered_to' =>
                            'Captura el nombre de quien recibe.',
                    ]);
        }

        return DB::transaction(
            function () use (
                $repairId,
                $tokenHash,
                $driverName,
                $deliveredTo,
                $data,
                $ipAddress,
                $userAgent
            ): RepairOrder {
                $repair =
                    RepairOrder::query()
                        ->whereKey(
                            $repairId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $metadata =
                    $this->
                        metadataArray(
                            $repair->
                                metadata
                            ?? []
                        );

                $public =
                    data_get(
                        $metadata,
                        'public_delivery'
                    );

                if (! is_array($public)) {
                    throw new
                        AuthorizationException(
                            'La liga de entrega no es válida.'
                        );
                }

                $storedHash =
                    (string) (
                        $public[
                            'token_sha256'
                        ]
                        ?? ''
                    );

                if (
                    $storedHash === ''
                    || ! hash_equals(
                        $storedHash,
                        $tokenHash
                    )
                ) {
                    throw new
                        AuthorizationException(
                            'La liga de entrega no es válida.'
                        );
                }

                if (
                    (string) (
                        $public['status']
                        ?? ''
                    ) !== 'pending'
                ) {
                    throw
                        ValidationException::
                            withMessages([
                                'delivery' =>
                                    'Esta liga ya no está disponible.',
                            ]);
                }

                if (
                    $this->isExpired(
                        (string) (
                            $public[
                                'expires_at'
                            ]
                            ?? ''
                        )
                    )
                ) {
                    throw
                        ValidationException::
                            withMessages([
                                'delivery' =>
                                    'La liga de entrega venció.',
                            ]);
                }

                if (
                    filled(
                        $repair->
                            delivered_at
                    )
                    || in_array(
                        (string) (
                            $repair->
                                workflow_stage
                            ?? ''
                        ),
                        [
                            'delivered',
                            'entregado',
                        ],
                        true
                    )
                ) {
                    throw
                        ValidationException::
                            withMessages([
                                'delivery' =>
                                    'La entrega ya fue registrada.',
                            ]);
                }

                /*
                 * ÚNICO bypass:
                 * actor autenticado/rol.
                 *
                 * Estado, SAL, bridge, politica,
                 * pago previo cuando aplique,
                 * crédito, no_charge, etc.
                 * siguen siendo validados por
                 * ServiceAccess.
                 */
                $canDeliver =
                    ServiceAccess::
                        withVerifiedPublicRepairDeliveryContext(
                            fn (): bool =>
                                ServiceAccess::
                                    canDeliverRepair(
                                        $repair
                                    )
                        );

                if (! $canDeliver) {
                    throw new
                        AuthorizationException(
                            'La reparación ya no está autorizada para entrega.'
                        );
                }

                /*
                 * ServiceRepairDeliveryService
                 * vuelve a ejecutar canDeliverRepair()
                 * DESPUÉS de su propio lock.
                 *
                 * El contexto existe únicamente
                 * durante esta llamada.
                 */
                $delivered =
                    ServiceAccess::
                        withVerifiedPublicRepairDeliveryContext(
                            fn (): RepairOrder =>
                                app(
                                    ServiceRepairDeliveryService::class
                                )->deliver(
                                    $repair,
                                    [
                                        'delivered_to' =>
                                            $deliveredTo,

                                        'delivery_notes' =>
                                            $data[
                                                'delivery_notes'
                                            ]
                                            ?? null,

                                        'delivery_files' =>
                                            $data[
                                                'delivery_files'
                                            ]
                                            ?? [],

                                        'delivery_signature_data' =>
                                            $data[
                                                'delivery_signature_data'
                                            ]
                                            ?? null,
                                    ]
                                )
                        );

                $delivered->refresh();

                $metadata =
                    $this->
                        metadataArray(
                            $delivered->
                                metadata
                            ?? []
                        );

                $current =
                    data_get(
                        $metadata,
                        'public_delivery'
                    );

                $current =
                    is_array($current)
                        ? $current
                        : [];

                $now = now();

                $metadata[
                    'public_delivery'
                ] = array_merge(
                    $current,
                    [
                        'status' =>
                            'completed',

                        /*
                         * El token en claro cifrado
                         * ya no es necesario.
                         */
                        'token_encrypted' =>
                            null,

                        'used_at' =>
                            $now->
                                toDateTimeString(),

                        'driver_name' =>
                            $driverName,

                        'delivered_to' =>
                            $deliveredTo,

                        'latitude' =>
                            isset(
                                $data['latitude']
                            )
                            && $data[
                                'latitude'
                            ] !== ''
                                ? (float)
                                    $data[
                                        'latitude'
                                    ]
                                : null,

                        'longitude' =>
                            isset(
                                $data['longitude']
                            )
                            && $data[
                                'longitude'
                            ] !== ''
                                ? (float)
                                    $data[
                                        'longitude'
                                    ]
                                : null,

                        'ip_address' =>
                            $ipAddress,

                        'user_agent' =>
                            $userAgent,

                        'source' =>
                            'public_repair_delivery',
                    ]
                );

                $delivered
                    ->forceFill([
                        'metadata' =>
                            $metadata,
                    ])
                    ->save();

                $this->logEvent(
                    repair:
                        $delivered,

                    eventType:
                        'repair_public_delivery_completed',

                    fromStatus:
                        'ready_for_delivery',

                    toStatus:
                        'delivered',

                    actorId:
                        null,

                    notes:
                        'El chofer registró la entrega física mediante la liga pública segura.',

                    metadata: [
                        'source' =>
                            'public_repair_delivery',

                        'token_sha256' =>
                            $tokenHash,

                        'driver_name' =>
                            $driverName,

                        'delivered_to' =>
                            $deliveredTo,

                        'latitude' =>
                            $metadata[
                                'public_delivery'
                            ][
                                'latitude'
                            ],

                        'longitude' =>
                            $metadata[
                                'public_delivery'
                            ][
                                'longitude'
                            ],
                    ],

                    ipAddress:
                        $ipAddress,

                    userAgent:
                        $userAgent
                );

                return
                    $delivered->fresh();
            }
        );
    }

    protected function isExpired(
        ?string $value
    ): bool {
        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return true;
        }

        try {
            return Carbon::
                parse($value)
                ->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    protected function metadataArray(
        mixed $raw
    ): array {
        if (is_array($raw)) {
            return $raw;
        }

        if (
            $raw instanceof
            \Illuminate\Contracts\Support\Arrayable
        ) {
            return
                $raw->toArray();
        }

        if (is_object($raw)) {
            return (array) $raw;
        }

        if (
            is_string($raw)
            && trim($raw) !== ''
        ) {
            $decoded =
                json_decode(
                    $raw,
                    true
                );

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return [];
    }

    protected function logEvent(
        RepairOrder $repair,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $actorId,
        string $notes,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        if (
            ! Schema::hasTable(
                'service_case_events'
            )
        ) {
            return;
        }

        $columns =
            Schema::
                getColumnListing(
                    'service_case_events'
                );

        $now = now();

        $row = [
            'company_id' =>
                $repair->company_id,

            'service_case_id' =>
                $repair->
                    service_case_id,

            'repair_order_id' =>
                $repair->getKey(),

            'event_type' =>
                $eventType,

            'from_status' =>
                $fromStatus,

            'to_status' =>
                $toStatus,

            'performed_by' =>
                $actorId,

            'performed_at' =>
                $now,

            'notes' =>
                $notes,

            'metadata' =>
                json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                ),

            'ip_address' =>
                $ipAddress,

            'user_agent' =>
                $userAgent,

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ];

        $safe =
            array_intersect_key(
                $row,
                array_flip(
                    $columns
                )
            );

        DB::table(
            'service_case_events'
        )->insert($safe);
    }
}
