<?php

namespace App\Support\Service;

use App\Models\ServiceCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicePickupPublicCaptureService
{
    /*
     * BEXIA_ATC_PUBLIC_PICKUP_CAPTURE_V5_83_4C4C
     *
     * La recoleccion publica:
     * - NO crea RepairOrder
     * - NO cambia received_at
     * - NO mueve el caso a en_diagnostico
     * - NO reemplaza producto/serie del ticket
     *
     * Guarda lo observado en:
     * service_cases.metadata.pickup_order.pickup
     */
    public function complete(
        ServiceCase $serviceCase,
        string $token,
        array $data,
        array $photos,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $storedPaths = [];

        try {
            return DB::transaction(
                function () use (
                    $serviceCase,
                    $token,
                    $data,
                    $photos,
                    $ipAddress,
                    $userAgent,
                    &$storedPaths
                ): array {
                    $case =
                        ServiceCase::query()
                            ->whereKey(
                                $serviceCase->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $metadata =
                        is_array(
                            $case->metadata
                        )
                            ? $case->metadata
                            : [];

                    $pickupOrder =
                        (array) (
                            $metadata[
                                'pickup_order'
                            ]
                            ?? []
                        );

                    $storedToken =
                        trim(
                            (string) (
                                $pickupOrder[
                                    'token'
                                ]
                                ?? ''
                            )
                        );

                    if (
                        $storedToken === ''
                        || ! hash_equals(
                            $storedToken,
                            $token
                        )
                    ) {
                        abort(404);
                    }

                    if (
                        (string) (
                            $pickupOrder[
                                'status'
                            ]
                            ?? ''
                        ) !== 'pending'
                    ) {
                        throw ValidationException::
                            withMessages([
                                'pickup' =>
                                    'Esta recolección ya fue registrada.',
                            ]);
                    }

                    if (
                        (string)
                            $case->attention_route
                            !== 'repair'
                        || (string)
                            $case->status
                            !== 'esperando_producto'
                    ) {
                        throw ValidationException::
                            withMessages([
                                'pickup' =>
                                    'El ticket ya no está pendiente de recolección.',
                            ]);
                    }

                    $repairCount =
                        DB::table(
                            'repair_orders'
                        )
                            ->where(
                                'service_case_id',
                                $case->id
                            )
                            ->count();

                    if ($repairCount !== 0) {
                        throw ValidationException::
                            withMessages([
                                'pickup' =>
                                    'El equipo ya tiene una orden técnica.',
                            ]);
                    }

                    $snapshot =
                        (array) (
                            $pickupOrder[
                                'reported_snapshot'
                            ]
                            ?? []
                        );

                    $driverName =
                        $this->cleanRequired(
                            $data[
                                'driver_name'
                            ]
                            ?? null,
                            'driver_name',
                            'Nombre del chofer'
                        );

                    $deliveredBy =
                        $this->cleanRequired(
                            $data[
                                'delivered_by'
                            ]
                            ?? null,
                            'delivered_by',
                            'Persona que entrega'
                        );

                    $pickupLocation =
                        $this->cleanRequired(
                            $data[
                                'pickup_location'
                            ]
                            ?? null,
                            'pickup_location',
                            'Lugar real de recolección'
                        );

                    $observedProduct =
                        $this->cleanRequired(
                            $data[
                                'observed_product_name'
                            ]
                            ?? null,
                            'observed_product_name',
                            'Producto / modelo observado'
                        );

                    $observedSerial =
                        trim(
                            (string) (
                                $data[
                                    'observed_serial_number'
                                ]
                                ?? ''
                            )
                        );

                    $physicalCondition =
                        trim(
                            (string) (
                                $data[
                                    'physical_condition'
                                ]
                                ?? ''
                            )
                        );

                    $conditionOptions = [
                        'sin_danos_visibles' =>
                            'Sin daños visibles',

                        'con_danos_visibles' =>
                            'Con daños visibles',

                        'desgaste_normal' =>
                            'Desgaste normal',

                        'otro' =>
                            'Otro',
                    ];

                    if (
                        ! array_key_exists(
                            $physicalCondition,
                            $conditionOptions
                        )
                    ) {
                        throw ValidationException::
                            withMessages([
                                'physical_condition' =>
                                    'Selecciona la condición física del equipo.',
                            ]);
                    }

                    $allowedAccessories = [
                        'ninguno',
                        'cargador',
                        'cable',
                        'bateria',
                        'llaves',
                        'control',
                        'otro',
                    ];

                    $accessories =
                        array_values(
                            array_unique(
                                array_map(
                                    static fn ($value) =>
                                        trim(
                                            (string)
                                                $value
                                        ),
                                    (array) (
                                        $data[
                                            'accessories'
                                        ]
                                        ?? []
                                    )
                                )
                            )
                        );

                    if ($accessories === []) {
                        throw ValidationException::
                            withMessages([
                                'accessories' =>
                                    'Indica los accesorios entregados o selecciona Ninguno.',
                            ]);
                    }

                    foreach (
                        $accessories
                        as $accessory
                    ) {
                        if (
                            ! in_array(
                                $accessory,
                                $allowedAccessories,
                                true
                            )
                        ) {
                            throw ValidationException::
                                withMessages([
                                    'accessories' =>
                                        'Se recibió un accesorio no válido.',
                                ]);
                        }
                    }

                    if (
                        in_array(
                            'ninguno',
                            $accessories,
                            true
                        )
                        && count(
                            $accessories
                        ) > 1
                    ) {
                        throw ValidationException::
                            withMessages([
                                'accessories' =>
                                    'Ninguno no puede combinarse con otros accesorios.',
                            ]);
                    }

                    $accessoriesOther =
                        trim(
                            (string) (
                                $data[
                                    'accessories_other'
                                ]
                                ?? ''
                            )
                        );

                    if (
                        in_array(
                            'otro',
                            $accessories,
                            true
                        )
                        && $accessoriesOther === ''
                    ) {
                        throw ValidationException::
                            withMessages([
                                'accessories_other' =>
                                    'Describe el otro accesorio entregado.',
                            ]);
                    }

                    $notes =
                        trim(
                            (string) (
                                $data['notes']
                                ?? ''
                            )
                        );

                    $signature =
                        $this->decodeSignature(
                            (string) (
                                $data[
                                    'customer_signature'
                                ]
                                ?? ''
                            )
                        );

                    $reportedProduct =
                        trim(
                            (string) (
                                $snapshot[
                                    'product_name'
                                ]
                                ?? $case->product_name
                                ?? ''
                            )
                        );

                    $reportedSerial =
                        trim(
                            (string) (
                                $snapshot[
                                    'serial_number'
                                ]
                                ?? $case->serial_number
                                ?? ''
                            )
                        );

                    $productDiffers =
                        $this->normalize(
                            $reportedProduct
                        )
                        !==
                        $this->normalize(
                            $observedProduct
                        );

                    $serialDiffers =
                        $this->normalize(
                            $reportedSerial
                        )
                        !==
                        $this->normalize(
                            $observedSerial
                        );

                    $now = now();

                    $photoAttachmentIds = [];

                    $photoDirectory =
                        'service-attachments/pickup/'
                        . $case->id;

                    foreach (
                        $photos
                        as $photo
                    ) {
                        if (! $photo) {
                            continue;
                        }

                        $extension =
                            strtolower(
                                (string)
                                    $photo
                                        ->extension()
                            );

                        if (
                            ! in_array(
                                $extension,
                                [
                                    'jpg',
                                    'jpeg',
                                    'png',
                                    'webp',
                                ],
                                true
                            )
                        ) {
                            throw ValidationException::
                                withMessages([
                                    'photos' =>
                                        'Una de las fotografías tiene un formato no válido.',
                                ]);
                        }

                        if ($extension === 'jpeg') {
                            $extension = 'jpg';
                        }

                        $storedName =
                            'evidencia-'
                            . Str::uuid()
                            . '.'
                            . $extension;

                        $storedPath =
                            Storage::disk(
                                'public'
                            )->putFileAs(
                                $photoDirectory,
                                $photo,
                                $storedName
                            );

                        if (! $storedPath) {
                            throw ValidationException::
                                withMessages([
                                    'photos' =>
                                        'No fue posible guardar una de las fotografías.',
                                ]);
                        }

                        $storedPaths[] =
                            $storedPath;

                        $photoAttachmentIds[] =
                            DB::table(
                                'service_attachments'
                            )->insertGetId([
                                'company_id' =>
                                    $case->company_id,

                                'service_case_id' =>
                                    $case->id,

                                'repair_order_id' =>
                                    null,

                                'uploaded_by' =>
                                    null,

                                'stage' =>
                                    'pickup',

                                'file_name' =>
                                    (string)
                                        $photo
                                            ->getClientOriginalName(),

                                'file_path' =>
                                    $storedPath,

                                'mime_type' =>
                                    (string)
                                        $photo
                                            ->getMimeType(),

                                'size' =>
                                    (int)
                                        $photo
                                            ->getSize(),

                                'is_customer_visible' =>
                                    false,

                                'notes' =>
                                    'Evidencia fotográfica de recolección.',

                                'created_at' =>
                                    $now,

                                'updated_at' =>
                                    $now,
                            ]);
                    }

                    $signaturePath =
                        $photoDirectory
                        . '/firma-cliente-'
                        . Str::uuid()
                        . '.png';

                    $signatureStored =
                        Storage::disk(
                            'public'
                        )->put(
                            $signaturePath,
                            $signature
                        );

                    if (! $signatureStored) {
                        throw ValidationException::
                            withMessages([
                                'customer_signature' =>
                                    'No fue posible guardar la firma del cliente.',
                            ]);
                    }

                    $storedPaths[] =
                        $signaturePath;

                    $signatureAttachmentId =
                        DB::table(
                            'service_attachments'
                        )->insertGetId([
                            'company_id' =>
                                $case->company_id,

                            'service_case_id' =>
                                $case->id,

                            'repair_order_id' =>
                                null,

                            'uploaded_by' =>
                                null,

                            'stage' =>
                                'pickup',

                            'file_name' =>
                                'firma-recoleccion-'
                                . $case->folio
                                . '.png',

                            'file_path' =>
                                $signaturePath,

                            'mime_type' =>
                                'image/png',

                            'size' =>
                                strlen(
                                    $signature
                                ),

                            'is_customer_visible' =>
                                false,

                            'notes' =>
                                'Firma del cliente en recolección.',

                            'created_at' =>
                                $now,

                            'updated_at' =>
                                $now,
                        ]);

                    $pickupData = [
                        'driver_name' =>
                            $driverName,

                        'delivered_by' =>
                            $deliveredBy,

                        'pickup_location' =>
                            $pickupLocation,

                        'observed_product_name' =>
                            $observedProduct,

                        'observed_serial_number' =>
                            $observedSerial !== ''
                                ? $observedSerial
                                : null,

                        'reported_product_name' =>
                            $reportedProduct !== ''
                                ? $reportedProduct
                                : null,

                        'reported_serial_number' =>
                            $reportedSerial !== ''
                                ? $reportedSerial
                                : null,

                        'product_differs' =>
                            $productDiffers,

                        'serial_differs' =>
                            $serialDiffers,

                        'physical_condition' =>
                            $physicalCondition,

                        'physical_condition_label' =>
                            $conditionOptions[
                                $physicalCondition
                            ],

                        'accessories' =>
                            $accessories,

                        'accessories_other' =>
                            $accessoriesOther !== ''
                                ? $accessoriesOther
                                : null,

                        'notes' =>
                            $notes !== ''
                                ? $notes
                                : null,

                        'evidence_attachment_ids' =>
                            $photoAttachmentIds,

                        'signature_attachment_id' =>
                            $signatureAttachmentId,

                        'customer_signed_at' =>
                            $now
                                ->toDateTimeString(),

                        'captured_at' =>
                            $now
                                ->toDateTimeString(),
                    ];

                    $pickupOrder[
                        'status'
                    ] = 'completed';

                    $pickupOrder[
                        'completed_at'
                    ] =
                        $now
                            ->toDateTimeString();

                    $pickupOrder[
                        'pickup'
                    ] =
                        $pickupData;

                    $metadata[
                        'pickup_order'
                    ] =
                        $pickupOrder;

                    /*
                     * IMPORTANTE:
                     * no modificar product_name,
                     * serial_number ni status del ticket.
                     */
                    $case->update([
                        'metadata' =>
                            $metadata,
                    ]);

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
                            'pickup_completed',

                        'from_status' =>
                            $case->status,

                        'to_status' =>
                            $case->status,

                        /*
                         * Captura pública:
                         * no existe usuario Bexia.
                         */
                        'performed_by' =>
                            null,

                        'performed_at' =>
                            $now,

                        'notes' =>
                            'Recolección registrada desde enlace público.',

                        'old_values' =>
                            json_encode(
                                [
                                    'pickup_status' =>
                                        'pending',
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'new_values' =>
                            json_encode(
                                [
                                    'pickup_status' =>
                                        'completed',

                                    'observed_product_name' =>
                                        $observedProduct,

                                    'observed_serial_number' =>
                                        $observedSerial !== ''
                                            ? $observedSerial
                                            : null,

                                    'product_differs' =>
                                        $productDiffers,

                                    'serial_differs' =>
                                        $serialDiffers,

                                    'evidence_attachment_ids' =>
                                        $photoAttachmentIds,

                                    'signature_attachment_id' =>
                                        $signatureAttachmentId,
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'metadata' =>
                            json_encode(
                                [
                                    'source' =>
                                        'public_pickup_order',

                                    'public_token_sha256' =>
                                        hash(
                                            'sha256',
                                            $token
                                        ),
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'ip_address' =>
                            $ipAddress,

                        'user_agent' =>
                            $userAgent,

                        'created_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]);

                    return [
                        'case_id' =>
                            $case->id,

                        'status' =>
                            'completed',

                        'product_differs' =>
                            $productDiffers,

                        'serial_differs' =>
                            $serialDiffers,

                        'photo_attachment_ids' =>
                            $photoAttachmentIds,

                        'signature_attachment_id' =>
                            $signatureAttachmentId,
                    ];
                }
            );
        } catch (\Throwable $e) {
            foreach (
                array_unique(
                    $storedPaths
                )
                as $storedPath
            ) {
                try {
                    Storage::disk(
                        'public'
                    )->delete(
                        $storedPath
                    );
                } catch (\Throwable) {
                    // No ocultar la excepcion original.
                }
            }

            throw $e;
        }
    }

    protected function cleanRequired(
        mixed $value,
        string $field,
        string $label
    ): string {
        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            throw ValidationException::
                withMessages([
                    $field =>
                        $label
                        . ' es obligatorio.',
                ]);
        }

        return $value;
    }

    protected function normalize(
        ?string $value
    ): string {
        $value =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    (string) $value
                )
                ?? ''
            );

        return mb_strtolower(
            $value,
            'UTF-8'
        );
    }

    protected function decodeSignature(
        string $value
    ): string {
        $prefix =
            'data:image/png;base64,';

        if (
            ! str_starts_with(
                $value,
                $prefix
            )
        ) {
            throw ValidationException::
                withMessages([
                    'customer_signature' =>
                        'La firma del cliente es obligatoria.',
                ]);
        }

        $encoded =
            substr(
                $value,
                strlen($prefix)
            );

        $binary =
            base64_decode(
                $encoded,
                true
            );

        if ($binary === false) {
            throw ValidationException::
                withMessages([
                    'customer_signature' =>
                        'La firma capturada no es válida.',
                ]);
        }

        $size =
            strlen(
                $binary
            );

        if ($size < 500) {
            throw ValidationException::
                withMessages([
                    'customer_signature' =>
                        'Captura la firma completa del cliente.',
                ]);
        }

        if (
            $size
            > 2 * 1024 * 1024
        ) {
            throw ValidationException::
                withMessages([
                    'customer_signature' =>
                        'La firma es demasiado grande.',
                ]);
        }

        $imageInfo =
            @getimagesizefromstring(
                $binary
            );

        if (
            ! is_array(
                $imageInfo
            )
            || (
                $imageInfo['mime']
                ?? ''
            ) !== 'image/png'
        ) {
            throw ValidationException::
                withMessages([
                    'customer_signature' =>
                        'La firma capturada no es una imagen PNG válida.',
                ]);
        }

        return $binary;
    }
}
