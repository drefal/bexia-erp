<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * BEXIA_ATC_ATOMIC_DELIVERY_SERVICE_V5_83_4C5E8
 *
 * Entrega física de una reparación.
 *
 * Garantías:
 * - revalida autorización al ejecutar;
 * - lockForUpdate;
 * - doble-submit protegido;
 * - evidencia obligatoria existente en storage;
 * - firma PNG válida;
 * - DB atómica para adjuntos/eventos/RepairOrder/ServiceCase;
 * - cleanup de archivos sin referencia si la transacción falla.
 */
class ServiceRepairDeliveryService
{
    public function deliver(
        int|RepairOrder $repairOrder,
        array $data
    ): RepairOrder {
        $repairId =
            $repairOrder instanceof RepairOrder
                ? (int) $repairOrder->getKey()
                : (int) $repairOrder;

        if ($repairId <= 0) {
            throw ValidationException::withMessages([
                'repair' =>
                    'No se encontró la reparación.',
            ]);
        }

        $deliveredTo =
            trim(
                (string) (
                    $data['delivered_to']
                    ?? ''
                )
            );

        if ($deliveredTo === '') {
            throw ValidationException::withMessages([
                'delivered_to' =>
                    'Captura el nombre de quien recibe.',
            ]);
        }

        if (mb_strlen($deliveredTo) > 255) {
            throw ValidationException::withMessages([
                'delivered_to' =>
                    'El nombre de quien recibe es demasiado largo.',
            ]);
        }

        $deliveryNotes =
            trim(
                (string) (
                    $data['delivery_notes']
                    ?? ''
                )
            );

        $deliveryPaths =
            $this->normalizeDeliveryPaths(
                (array) (
                    $data['delivery_files']
                    ?? []
                )
            );

        $signaturePath = null;

        try {
            if ($deliveryPaths === []) {
                throw ValidationException::withMessages([
                    'delivery_files' =>
                        'Agrega al menos una evidencia de entrega.',
                ]);
            }

            foreach ($deliveryPaths as $path) {
                if (
                    ! str_starts_with(
                        $path,
                        'service/delivery-files/'
                    )
                ) {
                    throw ValidationException::withMessages([
                        'delivery_files' =>
                            'Existe una evidencia de entrega con una ruta no válida.',
                    ]);
                }

                if (
                    ! Storage::disk('public')
                        ->exists($path)
                ) {
                    throw ValidationException::withMessages([
                        'delivery_files' =>
                            'Uno de los archivos de evidencia ya no está disponible. Vuelve a subirlo.',
                    ]);
                }
            }

            $signatureBinary =
                $this->decodeSignature(
                    $data[
                        'delivery_signature_data'
                    ] ?? null
                );

            $result =
                DB::transaction(
                    function () use (
                        $repairId,
                        $deliveredTo,
                        $deliveryNotes,
                        $deliveryPaths,
                        $signatureBinary,
                        &$signaturePath
                    ): RepairOrder {
                        $repair =
                            RepairOrder::query()
                                ->whereKey(
                                    $repairId
                                )
                                ->lockForUpdate()
                                ->firstOrFail();

                        /*
                         * El gate se vuelve a evaluar DESPUÉS
                         * de adquirir el lock.
                         */
                        if (
                            ! ServiceAccess::
                                canDeliverRepair(
                                    $repair
                                )
                        ) {
                            throw new AuthorizationException(
                                'La reparación ya no puede entregarse en su estado actual.'
                            );
                        }

                        if (
                            (string) (
                                $repair->
                                    workflow_stage
                                ?? ''
                            ) !==
                            'ready_for_delivery'
                            || (string) (
                                $repair->status
                                ?? ''
                            ) !==
                            'ready_for_delivery'
                        ) {
                            throw ValidationException::
                                withMessages([
                                    'repair' =>
                                        'La reparación ya no está lista para entrega.',
                                ]);
                        }

                        if (
                            ! empty(
                                $repair->
                                    delivered_at
                            )
                        ) {
                            throw ValidationException::
                                withMessages([
                                    'repair' =>
                                        'Esta reparación ya fue entregada.',
                                ]);
                        }

                        /*
                         * Evita duplicados y detecta estados
                         * parciales heredados.
                         */
                        $existingDeliveryAttachments =
                            Schema::hasTable(
                                'service_attachments'
                            )
                                ? DB::table(
                                    'service_attachments'
                                )
                                    ->where(
                                        'repair_order_id',
                                        $repair->id
                                    )
                                    ->whereIn(
                                        'stage',
                                        [
                                            'delivery',
                                            'delivery_signature',
                                        ]
                                    )
                                    ->count()
                                : 0;

                        $existingDeliveredEvents =
                            Schema::hasTable(
                                'service_case_events'
                            )
                                ? DB::table(
                                    'service_case_events'
                                )
                                    ->where(
                                        'repair_order_id',
                                        $repair->id
                                    )
                                    ->where(
                                        'event_type',
                                        'repair_delivered'
                                    )
                                    ->count()
                                : 0;

                        if (
                            $existingDeliveryAttachments > 0
                            || $existingDeliveredEvents > 0
                        ) {
                            throw ValidationException::
                                withMessages([
                                    'repair' =>
                                        'Ya existe evidencia o auditoría previa de entrega. Revisa el expediente antes de volver a confirmar.',
                                ]);
                        }

                        /*
                         * Primero se registran las evidencias
                         * dentro de la misma transacción DB.
                         */
                        foreach (
                            $deliveryPaths
                            as $path
                        ) {
                            $this->insertAttachment(
                                repair: $repair,
                                stage: 'delivery',
                                filePath: $path,
                                notes:
                                    'Evidencia de entrega'
                            );
                        }

                        $this->logEvent(
                            repair: $repair,
                            eventType:
                                'delivery_files_uploaded',
                            fromStatus:
                                'ready_for_delivery',
                            toStatus:
                                'ready_for_delivery',
                            notes:
                                'Se agregaron fotos y documentos de evidencia de entrega.'
                        );

                        $signaturePath =
                            $this->writeSignature(
                                repair: $repair,
                                binary:
                                    $signatureBinary
                            );

                        $this->insertAttachment(
                            repair: $repair,
                            stage:
                                'delivery_signature',
                            filePath:
                                $signaturePath,
                            notes:
                                'Firma digital de entrega'
                        );

                        $this->logEvent(
                            repair: $repair,
                            eventType:
                                'delivery_signature_captured',
                            fromStatus:
                                'ready_for_delivery',
                            toStatus:
                                'ready_for_delivery',
                            notes:
                                'Se capturó firma digital de entrega.'
                        );

                        $now = now();

                        $repair->update([
                            'status' =>
                                'delivered',

                            'workflow_stage' =>
                                'delivered',

                            'delivered_at' =>
                                $now,

                            'delivered_to' =>
                                $deliveredTo,

                            'delivery_notes' =>
                                $deliveryNotes !== ''
                                    ? $deliveryNotes
                                    : null,
                        ]);

                        $repair->refresh();

                        $this->logEvent(
                            repair: $repair,
                            eventType:
                                'repair_delivered',
                            fromStatus:
                                'ready_for_delivery',
                            toStatus:
                                'delivered',
                            notes:
                                'La reparación fue entregada al cliente.'
                        );

                        /*
                         * Este servicio también usa transacción.
                         * Laravel lo ejecuta anidado sobre la
                         * misma conexión; si falla, la entrega
                         * completa debe abortarse.
                         */
                        app(
                            ServiceRepairCaseLifecycleService::class
                        )->closeCaseAfterDelivery(
                            $repair
                        );

                        return $repair->fresh();
                    }
                );

            return $result;
        } catch (\Throwable $exception) {
            /*
             * Storage no participa en la transacción DB.
             * Borra solamente archivos que NO quedaron
             * referenciados por ningún attachment.
             */
            if (
                is_string($signaturePath)
                && $signaturePath !== ''
            ) {
                $this->deleteIfUnreferenced(
                    $signaturePath
                );
            }

            foreach ($deliveryPaths as $path) {
                $this->deleteIfUnreferenced(
                    $path
                );
            }

            throw $exception;
        }
    }

    protected function normalizeDeliveryPaths(
        array $paths
    ): array {
        $normalized = [];

        foreach ($paths as $path) {
            if (is_array($path)) {
                $path =
                    $path['path']
                    ?? $path['file']
                    ?? $path['name']
                    ?? null;
            }

            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);

            if ($path === '') {
                continue;
            }

            $normalized[] = $path;
        }

        return array_values(
            array_unique(
                $normalized
            )
        );
    }

    protected function decodeSignature(
        mixed $signatureData
    ): string {
        if (
            ! is_string($signatureData)
            || trim($signatureData) === ''
        ) {
            throw ValidationException::
                withMessages([
                    'delivery_signature_data' =>
                        'Captura la firma digital de quien recibe.',
                ]);
        }

        $prefix =
            'data:image/png;base64,';

        if (
            ! str_starts_with(
                $signatureData,
                $prefix
            )
        ) {
            throw ValidationException::
                withMessages([
                    'delivery_signature_data' =>
                        'La firma digital no tiene un formato PNG válido.',
                ]);
        }

        $binary =
            base64_decode(
                substr(
                    $signatureData,
                    strlen($prefix)
                ),
                true
            );

        if (
            $binary === false
            || $binary === ''
        ) {
            throw ValidationException::
                withMessages([
                    'delivery_signature_data' =>
                        'No fue posible leer la firma digital.',
                ]);
        }

        if (
            ! str_starts_with(
                $binary,
                "\x89PNG\r\n\x1a\n"
            )
        ) {
            throw ValidationException::
                withMessages([
                    'delivery_signature_data' =>
                        'La firma digital no corresponde a una imagen PNG válida.',
                ]);
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            throw ValidationException::
                withMessages([
                    'delivery_signature_data' =>
                        'La firma digital excede el tamaño permitido.',
                ]);
        }

        return $binary;
    }

    protected function writeSignature(
        RepairOrder $repair,
        string $binary
    ): string {
        $folio =
            preg_replace(
                '/[^A-Za-z0-9_\-]/',
                '_',
                (string) (
                    $repair->folio
                    ?? (
                        'repair_'
                        . $repair->id
                    )
                )
            );

        $filePath =
            'service/signatures/delivery/'
            . $folio
            . '-'
            . Str::uuid()->toString()
            . '.png';

        $stored =
            Storage::disk('public')
                ->put(
                    $filePath,
                    $binary
                );

        if (! $stored) {
            throw ValidationException::
                withMessages([
                    'delivery_signature_data' =>
                        'No fue posible guardar la firma digital.',
                ]);
        }

        return $filePath;
    }

    protected function insertAttachment(
        RepairOrder $repair,
        string $stage,
        string $filePath,
        string $notes
    ): void {
        if (
            ! Schema::hasTable(
                'service_attachments'
            )
        ) {
            throw new \RuntimeException(
                'No está disponible la tabla de evidencias de servicio.'
            );
        }

        if (
            DB::table('service_attachments')
                ->where(
                    'repair_order_id',
                    $repair->id
                )
                ->where(
                    'stage',
                    $stage
                )
                ->where(
                    'file_path',
                    $filePath
                )
                ->exists()
        ) {
            throw ValidationException::
                withMessages([
                    'delivery_files' =>
                        'La evidencia ya está registrada en el expediente.',
                ]);
        }

        $disk =
            Storage::disk('public');

        $mimeType = null;
        $size = null;

        try {
            if ($disk->exists($filePath)) {
                $mimeType =
                    $disk->mimeType(
                        $filePath
                    );

                $size =
                    $disk->size(
                        $filePath
                    );
            }
        } catch (\Throwable) {
            $mimeType = null;
            $size = null;
        }

        $columns =
            Schema::getColumnListing(
                'service_attachments'
            );

        $now = now();

        $payload = [
            'company_id' =>
                $repair->company_id,

            'service_case_id' =>
                $repair->service_case_id,

            'repair_order_id' =>
                $repair->id,

            'uploaded_by' =>
                auth()->id(),

            'stage' =>
                $stage,

            'file_name' =>
                basename(
                    $filePath
                ),

            'file_path' =>
                $filePath,

            'mime_type' =>
                $mimeType,

            'size' =>
                $size,

            'is_customer_visible' =>
                false,

            'notes' =>
                $notes,

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ];

        $safe =
            array_intersect_key(
                $payload,
                array_flip(
                    $columns
                )
            );

        if ($safe === []) {
            throw new \RuntimeException(
                'No fue posible construir el attachment de entrega.'
            );
        }

        DB::table(
            'service_attachments'
        )->insert($safe);
    }

    protected function logEvent(
        RepairOrder $repair,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        string $notes
    ): void {
        if (
            ! Schema::hasTable(
                'service_case_events'
            )
        ) {
            return;
        }

        $columns =
            Schema::getColumnListing(
                'service_case_events'
            );

        $now = now();

        $payload = [
            'company_id' =>
                $repair->company_id,

            'service_case_id' =>
                $repair->service_case_id,

            'repair_order_id' =>
                $repair->id,

            'event_type' =>
                $eventType,

            'from_status' =>
                $fromStatus,

            'to_status' =>
                $toStatus,

            'performed_by' =>
                auth()->id(),

            'performed_at' =>
                $now,

            'user_id' =>
                auth()->id(),

            'description' =>
                $notes,

            'notes' =>
                $notes,

            'metadata' =>
                json_encode(
                    [
                        'source' =>
                            'atomic_repair_delivery',
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ];

        $safe =
            array_intersect_key(
                $payload,
                array_flip(
                    $columns
                )
            );

        if ($safe !== []) {
            DB::table(
                'service_case_events'
            )->insert($safe);
        }
    }

    protected function deleteIfUnreferenced(
        string $filePath
    ): void {
        $filePath =
            trim($filePath);

        if ($filePath === '') {
            return;
        }

        $referenced =
            Schema::hasTable(
                'service_attachments'
            )
                && DB::table(
                    'service_attachments'
                )
                    ->where(
                        'file_path',
                        $filePath
                    )
                    ->exists();

        if ($referenced) {
            return;
        }

        try {
            Storage::disk('public')
                ->delete(
                    $filePath
                );
        } catch (\Throwable) {
            /*
             * No ocultar la excepción original
             * de la entrega por un fallo de cleanup.
             */
        }
    }
}
