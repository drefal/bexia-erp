<?php

namespace App\Support\Service;

use App\Models\ServiceCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceCaseDirectAttentionService
{
    public const EVENT_RESPONSE =
        'respuesta_atencion_directa';

    public const EVENT_RESPONSE_VALIDATED =
        'respuesta_atencion_directa_validada';

    public const RESOLUTION_TYPES = [
        'informacion_proporcionada' =>
            'Información proporcionada',

        'soporte_completado' =>
            'Soporte técnico completado',

        'configuracion_completada' =>
            'Configuración completada',

        'seguimiento_concluido' =>
            'Seguimiento concluido',

        'cliente_orientado' =>
            'Cliente orientado',

        'no_procede' =>
            'No procede',

        'otro' =>
            'Otro',
    ];

    public static function resolutionTypeLabel(
        ?string $type
    ): string {
        if (! $type) {
            return 'Pendiente';
        }

        return self::RESOLUTION_TYPES[$type]
            ?? $type;
    }

    public function registerResponse(
        ServiceCase $serviceCase,
        string $notes,
        mixed $image = null
    ): ServiceCase {
        // BEXIA_ATC_ASSIGNED_TECH_RESPONSE_ACTOR_V5_82_P7H32A4B2
        //
        // Esta acción tiene autorización propia mediante
        // assertCanRegisterResponse(). El técnico asignado no
        // necesita recibir permisos CRUD generales del ticket.
        $actorId = (int) auth()->id();

        if ($actorId <= 0) {
            throw new AuthorizationException(
                'Debes iniciar sesión para registrar una respuesta.'
            );
        }

        $notes = trim($notes);

        if ($notes === '') {
            throw ValidationException::withMessages([
                'response_notes' =>
                    'Captura la respuesta proporcionada.',
            ]);
        }

        return DB::transaction(function () use (
            $serviceCase,
            $actorId,
            $notes,
            $image
        ): ServiceCase {
            $case = $this->lockCase($serviceCase);

            $this->assertOpenDirectCase($case);
            $this->assertCanRegisterResponse($case);

            $oldStatus = (string) $case->status;
            $now = now();

            $updates = [
                'status' => 'en_revision',
            ];

            if (! $case->first_response_at) {
                $updates['first_response_at'] = $now;
            }

            $case->update($updates);

            $this->logEvent(
                case: $case,
                eventType: self::EVENT_RESPONSE,
                fromStatus: $oldStatus,
                toStatus: 'en_revision',
                actorId: $actorId,
                notes: $notes,
                oldValues: [
                    'first_response_at' =>
                        $case->getOriginal(
                            'first_response_at'
                        ),
                ],
                newValues: [
                    'first_response_at' =>
                        $case->fresh()->first_response_at,
                ]
            );

            ServiceAccess::saveUploadedAttachments(
                companyId: $case->company_id,
                serviceCaseId: $case->id,
                repairOrderId: null,
                files: $image,
                stage: 'direct_attention_response',
                isCustomerVisible: false
            );

            /*
             * Si la respuesta fue registrada directamente por
             * Encargado de Técnicos o Supervisor, queda validada
             * automáticamente y puede procederse al cierre.
             *
             * Si fue registrada por el técnico asignado, queda
             * pendiente de validación.
             */
            if (
                ServiceAccess::canValidateDirectServiceCaseResponse(
                    $case
                )
            ) {
                $responseEvent =
                    $this->latestResponseEvent($case);

                if (
                    $responseEvent
                    && ! $this->responseEventIsValidated(
                        $case,
                        $responseEvent
                    )
                ) {
                    $this->logResponseValidation(
                        case: $case,
                        responseEvent: $responseEvent,
                        actorId: $actorId,
                        mode: 'automatic',
                        notes:
                            'Respuesta validada automáticamente porque fue registrada por Encargado de Técnicos o Supervisor.'
                    );
                }
            }

            return $case->fresh();
        });
    }

    public function latestResponseIsValidated(
        ServiceCase $serviceCase
    ): bool {
        $responseEvent =
            $this->latestResponseEvent(
                $serviceCase
            );

        if (! $responseEvent) {
            return false;
        }

        return $this->responseEventIsValidated(
            $serviceCase,
            $responseEvent
        );
    }

    public function validateLatestResponse(
        ServiceCase $serviceCase,
        string $notes = ''
    ): ServiceCase {
        $actorId = $this->actorId();

        return DB::transaction(function () use (
            $serviceCase,
            $actorId,
            $notes
        ): ServiceCase {
            $case =
                $this->lockCase(
                    $serviceCase
                );

            $this->assertOpenDirectCase($case);

            if (
                ! ServiceAccess::canValidateDirectServiceCaseResponse(
                    $case
                )
            ) {
                throw new AuthorizationException(
                    'Sólo el Encargado de Técnicos o el Supervisor pueden validar una respuesta.'
                );
            }

            $responseEvent =
                $this->latestResponseEvent(
                    $case
                );

            if (! $responseEvent) {
                throw ValidationException::withMessages([
                    'validation_notes' =>
                        'No existe una respuesta pendiente de validar.',
                ]);
            }

            if (
                $this->responseEventIsValidated(
                    $case,
                    $responseEvent
                )
            ) {
                throw ValidationException::withMessages([
                    'validation_notes' =>
                        'La última respuesta ya fue validada.',
                ]);
            }

            $notes = trim($notes);

            if ($notes === '') {
                $notes =
                    'Respuesta del técnico validada por Encargado de Técnicos o Supervisor.';
            }

            $this->logResponseValidation(
                case: $case,
                responseEvent: $responseEvent,
                actorId: $actorId,
                mode: 'manual',
                notes: $notes
            );

            return $case->fresh();
        });
    }

    public function waitForCustomer(
        ServiceCase $serviceCase,
        string $notes
    ): ServiceCase {
        $actorId = $this->actorId();

        $notes = trim($notes);

        if ($notes === '') {
            throw ValidationException::withMessages([
                'waiting_notes' =>
                    'Indica qué información se solicitó al cliente.',
            ]);
        }

        return DB::transaction(function () use (
            $serviceCase,
            $actorId,
            $notes
        ): ServiceCase {
            $case = $this->lockCase($serviceCase);

            $this->assertOpenDirectCase($case);

            $oldStatus = (string) $case->status;
            $now = now();

            $updates = [
                'status' => 'esperando_cliente',
            ];

            if (! $case->first_response_at) {
                $updates['first_response_at'] = $now;
            }

            $case->update($updates);

            $this->logEvent(
                case: $case,
                eventType: 'ticket_esperando_cliente',
                fromStatus: $oldStatus,
                toStatus: 'esperando_cliente',
                actorId: $actorId,
                notes: $notes,
                oldValues: null,
                newValues: [
                    'status' => 'esperando_cliente',
                ]
            );

            return $case->fresh();
        });
    }

    public function resolveAndClose(
        ServiceCase $serviceCase,
        string $resolutionType,
        string $notes
    ): ServiceCase {
        $actorId = $this->actorId();

        if (
            ! array_key_exists(
                $resolutionType,
                self::RESOLUTION_TYPES
            )
        ) {
            throw ValidationException::withMessages([
                'resolution_type' =>
                    'Selecciona un tipo de resolución válido.',
            ]);
        }

        $notes = trim($notes);

        if ($notes === '') {
            throw ValidationException::withMessages([
                'resolution_notes' =>
                    'Captura la solución proporcionada.',
            ]);
        }

        return DB::transaction(function () use (
            $serviceCase,
            $resolutionType,
            $notes,
            $actorId
        ): ServiceCase {
            $case = $this->lockCase($serviceCase);

            $this->assertOpenDirectCase($case);
            $this->assertCanCloseDirectCase($case);
            $this->assertLatestResponseValidated($case);

            $oldStatus = (string) $case->status;
            $now = now();

            $updates = [
                'status' => 'cerrado',
                'resolution_type' =>
                    $resolutionType,
                'resolution_notes' => $notes,
                'closed_at' => $now,
                'closed_by' => $actorId,
            ];

            if (! $case->first_response_at) {
                $updates['first_response_at'] = $now;
            }

            $case->update($updates);

            $this->logEvent(
                case: $case,
                eventType:
                    'ticket_resuelto_sin_reparacion',
                fromStatus: $oldStatus,
                toStatus: 'cerrado',
                actorId: $actorId,
                notes: $notes,
                oldValues: [
                    'resolution_type' =>
                        $case->getOriginal(
                            'resolution_type'
                        ),
                    'resolution_notes' =>
                        $case->getOriginal(
                            'resolution_notes'
                        ),
                ],
                newValues: [
                    'resolution_type' =>
                        $resolutionType,
                    'resolution_notes' =>
                        $notes,
                    'closed_at' =>
                        (string) $case->closed_at,
                ]
            );

            return $case->fresh();
        });
    }

    public function reopen(
        ServiceCase $serviceCase,
        string $reason
    ): ServiceCase {
        $actorId = $this->actorId();

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reopen_reason' =>
                    'Captura el motivo de reapertura.',
            ]);
        }

        return DB::transaction(function () use (
            $serviceCase,
            $reason,
            $actorId
        ): ServiceCase {
            $case = $this->lockCase($serviceCase);

            if (
                (string) $case->attention_route
                    !== 'non_repair'
            ) {
                throw ValidationException::withMessages([
                    'reopen_reason' =>
                        'La reapertura de atención directa sólo aplica a tickets sin reparación.',
                ]);
            }

            if ((string) $case->status !== 'cerrado') {
                throw ValidationException::withMessages([
                    'reopen_reason' =>
                        'Sólo se puede reabrir un ticket cerrado.',
                ]);
            }

            $oldResolutionType =
                $case->resolution_type;

            $oldResolutionNotes =
                $case->resolution_notes;

            $newStatus = (
                $case->assigned_employee_id
                || $case->assigned_user_id
            )
                ? 'asignado'
                : 'en_revision';

            $case->update([
                'status' => $newStatus,
                'resolution_type' => null,
                'resolution_notes' => null,
                'closed_at' => null,
                'closed_by' => null,
            ]);

            $this->logEvent(
                case: $case,
                eventType:
                    'ticket_reabierto_sin_reparacion',
                fromStatus: 'cerrado',
                toStatus: $newStatus,
                actorId: $actorId,
                notes: $reason,
                oldValues: [
                    'resolution_type' =>
                        $oldResolutionType,
                    'resolution_notes' =>
                        $oldResolutionNotes,
                ],
                newValues: [
                    'status' => $newStatus,
                ]
            );

            return $case->fresh();
        });
    }

    protected function actorId(): int
    {
        if (! ServiceAccess::can('service.cases.update')) {
            throw new AuthorizationException(
                'No tienes permiso para atender tickets.'
            );
        }

        $actorId = (int) auth()->id();

        if ($actorId <= 0) {
            throw new AuthorizationException(
                'Debes iniciar sesión para atender el ticket.'
            );
        }

        return $actorId;
    }

    protected function lockCase(
        ServiceCase $serviceCase
    ): ServiceCase {
        $case = ServiceCase::query()
            ->whereKey($serviceCase->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $companyId =
            ServiceAccess::currentCompanyId();

        if (
            $companyId
            && (int) $case->company_id
                !== (int) $companyId
        ) {
            throw new AuthorizationException(
                'El ticket no pertenece a la empresa actual.'
            );
        }

        return $case;
    }

    protected function latestResponseEvent(
        ServiceCase $case
    ): ?object {
        if (
            ! Schema::hasTable(
                'service_case_events'
            )
        ) {
            return null;
        }

        return $case->events()
            ->where(
                'event_type',
                self::EVENT_RESPONSE
            )
            ->orderByDesc('id')
            ->first();
    }

    protected function responseEventIsValidated(
        ServiceCase $case,
        object $responseEvent
    ): bool {
        if (
            ! Schema::hasTable(
                'service_case_events'
            )
        ) {
            return false;
        }

        $validationEvents =
            $case->events()
                ->where(
                    'event_type',
                    self::EVENT_RESPONSE_VALIDATED
                )
                ->orderByDesc('id')
                ->get([
                    'id',
                    'new_values',
                ]);

        foreach ($validationEvents as $validationEvent) {
            $values =
                $validationEvent->new_values;

            if (is_string($values)) {
                $values =
                    json_decode(
                        $values,
                        true
                    ) ?: [];
            }

            if (! is_array($values)) {
                $values = [];
            }

            if (
                (int) (
                    $values['response_event_id']
                    ?? 0
                )
                === (int) $responseEvent->id
            ) {
                return true;
            }
        }

        return false;
    }

    protected function logResponseValidation(
        ServiceCase $case,
        object $responseEvent,
        int $actorId,
        string $mode,
        string $notes
    ): void {
        $this->logEvent(
            case: $case,
            eventType:
                self::EVENT_RESPONSE_VALIDATED,
            fromStatus:
                (string) $case->status,
            toStatus:
                (string) $case->status,
            actorId: $actorId,
            notes: $notes,
            oldValues: null,
            newValues: [
                'response_event_id' =>
                    (int) $responseEvent->id,
                'response_performed_by' =>
                    (int) (
                        $responseEvent->performed_by
                        ?? 0
                    ),
                'validated_by' =>
                    $actorId,
                'validation_mode' =>
                    $mode,
            ]
        );
    }

    protected function assertCanRegisterResponse(
        ServiceCase $case
    ): void {
        if (
            ! ServiceAccess::canRespondToDirectServiceCase(
                $case
            )
        ) {
            throw new AuthorizationException(
                'Sólo el técnico asignado, el Encargado de Técnicos o el Supervisor pueden registrar una respuesta al cliente.'
            );
        }
    }

    protected function assertCanCloseDirectCase(
        ServiceCase $case
    ): void {
        if (
            ! ServiceAccess::canCloseDirectServiceCase(
                $case
            )
        ) {
            throw new AuthorizationException(
                'Sólo el Encargado de Técnicos o el Supervisor pueden resolver y cerrar una atención directa.'
            );
        }
    }

    protected function assertLatestResponseValidated(
        ServiceCase $case
    ): void {
        $responseEvent =
            $this->latestResponseEvent(
                $case
            );

        if (! $responseEvent) {
            throw ValidationException::withMessages([
                'resolution_notes' =>
                    'Registra al menos una respuesta al cliente antes de resolver y cerrar el ticket.',
            ]);
        }

        if (
            ! $this->responseEventIsValidated(
                $case,
                $responseEvent
            )
        ) {
            throw ValidationException::withMessages([
                'resolution_notes' =>
                    'La última respuesta del técnico debe ser validada por el Encargado de Técnicos o el Supervisor antes de cerrar el ticket.',
            ]);
        }
    }

    protected function assertOpenDirectCase(
        ServiceCase $case
    ): void {
        if (
            (string) $case->attention_route
                !== 'non_repair'
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'Esta acción sólo aplica a atención sin reparación.',
            ]);
        }

        if (
            $case->repairOrders()
                ->withTrashed()
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'El ticket ya tiene una reparación vinculada.',
            ]);
        }

        if (
            in_array(
                (string) $case->status,
                [
                    'cerrado',
                    'rechazado',
                    'cancelado',
                    'entregado',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'El ticket ya se encuentra terminado.',
            ]);
        }
    }

    protected function logEvent(
        ServiceCase $case,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        int $actorId,
        string $notes,
        ?array $oldValues,
        ?array $newValues
    ): void {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        $now = now();

        DB::table('service_case_events')->insert([
            'company_id' => $case->company_id,
            'service_case_id' => $case->id,
            'repair_order_id' => null,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => $actorId,
            'performed_at' => $now,
            'notes' => $notes,

            'old_values' => $oldValues
                ? json_encode(
                    $oldValues,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null,

            'new_values' => $newValues
                ? json_encode(
                    $newValues,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null,

            'metadata' => json_encode([
                'source' =>
                    'service_case_direct_attention',
            ], JSON_UNESCAPED_UNICODE),

            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
