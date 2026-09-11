<?php

namespace App\Support\Service;

use App\Models\ExitWarehouse;
use App\Models\RepairOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
 * BEXIA_ATC_REPAIR_EXIT_DOCUMENT_SERVICE_V5_83_4C5G3
 *
 * Genera la autorización documental para retirar
 * físicamente de la ubicación un equipo del cliente.
 *
 * MUY IMPORTANTE:
 *
 * Este servicio NO:
 * - crea SaleDelivery;
 * - crea ExitDelivery;
 * - crea StockMovement;
 * - crea StockMovementLine;
 * - modifica StockQuant;
 * - marca serie como vendida.
 *
 * El equipo está bajo custodia de servicio y
 * la salida sólo documenta su devolución.
 */
class ServiceRepairExitDocumentService
{
    public function authorize(
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

        $exitWarehouseId =
            (int) (
                $data[
                    'origin_exit_warehouse_id'
                ]
                ?? 0
            );

        $manualLocation =
            trim(
                (string) (
                    $data[
                        'origin_location_label'
                    ]
                    ?? ''
                )
            );

        $notes =
            trim(
                (string) (
                    $data[
                        'exit_document_notes'
                    ]
                    ?? ''
                )
            );

        if (
            $exitWarehouseId <= 0
            && $manualLocation === ''
        ) {
            throw ValidationException::withMessages([
                'origin_location_label' =>
                    'Selecciona o captura la ubicación de salida.',
            ]);
        }

        return DB::transaction(
            function () use (
                $repairId,
                $exitWarehouseId,
                $manualLocation,
                $notes
            ): RepairOrder {
                $repair =
                    RepairOrder::query()
                        ->whereKey(
                            $repairId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    ! ServiceAccess::
                        canPrepareRepairExitDocument(
                            $repair
                        )
                ) {
                    throw new AuthorizationException(
                        'La salida ATC no puede autorizarse en el estado actual de la reparación.'
                    );
                }

                if (
                    ServiceAccess::
                        hasAuthorizedRepairExitDocument(
                            $repair
                        )
                ) {
                    throw ValidationException::
                        withMessages([
                            'repair' =>
                                'Esta reparación ya tiene una salida ATC autorizada.',
                        ]);
                }

                $originWarehouse = null;

                if ($exitWarehouseId > 0) {
                    $originWarehouse =
                        ExitWarehouse::query()
                            ->whereKey(
                                $exitWarehouseId
                            )
                            ->where(
                                'company_id',
                                $repair->company_id
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->whereIn(
                                'usage_type',
                                [
                                    'envio',
                                    'ambos',
                                ]
                            )
                            ->first();

                    if (! $originWarehouse) {
                        throw ValidationException::
                            withMessages([
                                'origin_exit_warehouse_id' =>
                                    'La ubicación seleccionada no está disponible para esta empresa.',
                            ]);
                    }
                }

                $originLabel =
                    $originWarehouse
                        ? trim(
                            (string)
                                $originWarehouse->name
                        )
                        : $manualLocation;

                if ($originLabel === '') {
                    throw ValidationException::
                        withMessages([
                            'origin_location_label' =>
                                'No se pudo determinar la ubicación de salida.',
                        ]);
                }

                $case = null;

                if (
                    filled(
                        $repair->service_case_id
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
                            ->first();
                }

                $actorId =
                    (int) auth()->id();

                if ($actorId <= 0) {
                    throw new AuthorizationException(
                        'Debes iniciar sesión para autorizar la salida.'
                    );
                }

                $actorName =
                    trim(
                        (string) (
                            auth()->user()?->name
                            ?? ''
                        )
                    );

                if ($actorName === '') {
                    $actorName =
                        'Usuario #'
                        . $actorId;
                }

                $customerName =
                    trim(
                        (string) (
                            $case->contact_name
                            ?? ''
                        )
                    );

                if ($customerName === '') {
                    $customerName =
                        'Cliente del ticket '
                        . (
                            $case->folio
                            ?? ''
                        );
                }

                $repairFolio =
                    trim(
                        (string) (
                            $repair->folio
                            ?? ''
                        )
                    );

                $folioSuffix =
                    preg_replace(
                        '/^REP-/i',
                        '',
                        $repairFolio
                    );

                $folioSuffix =
                    trim(
                        (string)
                            $folioSuffix
                    );

                if ($folioSuffix === '') {
                    $folioSuffix =
                        str_pad(
                            (string)
                                $repair->id,
                            8,
                            '0',
                            STR_PAD_LEFT
                        );
                }

                $exitFolio =
                    'SAL-ATC-'
                    . $folioSuffix;

                $now = now();

                $rawMetadata =
                    $repair->metadata;

                if (is_array($rawMetadata)) {
                    $metadata =
                        $rawMetadata;
                } elseif (
                    is_string($rawMetadata)
                    && trim($rawMetadata) !== ''
                ) {
                    $decoded =
                        json_decode(
                            $rawMetadata,
                            true
                        );

                    $metadata =
                        is_array($decoded)
                            ? $decoded
                            : [];
                } elseif (is_object($rawMetadata)) {
                    $metadata =
                        (array) $rawMetadata;
                } else {
                    $metadata = [];
                }

                $document = [
                    'version' =>
                        'V5.83.4c5g3',

                    'document_kind' =>
                        'customer_owned_equipment_custody_exit',

                    'source_system' =>
                        'atc_custody_exit',

                    'status' =>
                        'authorized',

                    'folio' =>
                        $exitFolio,

                    'company_id' =>
                        (int)
                            $repair->company_id,

                    'service_case_id' =>
                        filled(
                            $repair->
                                service_case_id
                        )
                            ? (int)
                                $repair->
                                    service_case_id
                            : null,

                    'service_case_folio' =>
                        $case->folio
                        ?? null,

                    'repair_order_id' =>
                        (int) $repair->id,

                    'repair_folio' =>
                        $repairFolio !== ''
                            ? $repairFolio
                            : null,

                    'customer_id' =>
                        filled(
                            $repair->customer_id
                        )
                            ? (int)
                                $repair->customer_id
                            : null,

                    'customer_name' =>
                        $customerName,

                    'product_id' =>
                        filled(
                            $repair->product_id
                        )
                            ? (int)
                                $repair->product_id
                            : null,

                    'product_name' =>
                        $repair->product_name
                        ?? null,

                    'serial_number' =>
                        $repair->serial_number
                        ?? null,

                    'lot_number' =>
                        $repair->lot_number
                        ?? null,

                    'origin_exit_warehouse_id' =>
                        $originWarehouse
                            ? (int)
                                $originWarehouse->id
                            : null,

                    'origin_exit_warehouse_code' =>
                        $originWarehouse
                            ? (
                                $originWarehouse->code
                                ?? null
                            )
                            : null,

                    'origin_location_label' =>
                        $originLabel,

                    'destination_label' =>
                        'Cliente / exterior',

                    'reason' =>
                        'Devolución de equipo propiedad del cliente después de servicio o reparación.',

                    'notes' =>
                        $notes !== ''
                            ? $notes
                            : null,

                    'authorized_by_user_id' =>
                        $actorId,

                    'authorized_by_name' =>
                        $actorName,

                    'authorized_at' =>
                        $now->
                            toDateTimeString(),

                    /*
                     * Protección explícita.
                     */
                    'ownership' =>
                        'customer',

                    'inventory_effect' =>
                        false,

                    'no_stock_movement' =>
                        true,

                    'stock_movement_id' =>
                        null,

                    'sale_delivery_id' =>
                        null,

                    'exit_delivery_id' =>
                        null,

                    'form_submission_id' =>
                        null,
                ];

                $metadata[
                    'atc_exit_document'
                ] = $document;

                $repair->update([
                    'metadata' =>
                        $metadata,
                ]);

                $repair->refresh();

                /*
                 * Auditoría ATC.
                 */
                if (
                    Schema::hasTable(
                        'service_case_events'
                    )
                ) {
                    DB::table(
                        'service_case_events'
                    )->insert([
                        'company_id' =>
                            $repair->company_id,

                        'service_case_id' =>
                            $repair->
                                service_case_id,

                        'repair_order_id' =>
                            $repair->id,

                        'event_type' =>
                            'repair_exit_document_authorized',

                        'from_status' =>
                            'ready_for_delivery',

                        'to_status' =>
                            'ready_for_delivery',

                        'performed_by' =>
                            $actorId,

                        'performed_at' =>
                            $now,

                        'notes' =>
                            'Se autorizó la salida física del equipo mediante '
                            . $exitFolio
                            . '. Sin afectación de inventario.',

                        'old_values' =>
                            json_encode(
                                [
                                    'atc_exit_document' =>
                                        null,
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'new_values' =>
                            json_encode(
                                [
                                    'atc_exit_document' =>
                                        $document,
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'metadata' =>
                            json_encode(
                                [
                                    'source' =>
                                        'atc_exit_document',

                                    'inventory_effect' =>
                                        false,

                                    'stock_movement_id' =>
                                        null,
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),

                        'ip_address' =>
                            request()->ip(),

                        'user_agent' =>
                            request()->
                                userAgent(),

                        'created_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]);
                }

                return $repair->fresh();
            }
        );
    }
}
