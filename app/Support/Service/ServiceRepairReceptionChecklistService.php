<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceRepairReceptionChecklistService
{
    public const PHYSICAL_CONDITIONS = [
        'sin_danos_visibles' =>
            'Sin daños visibles',

        'con_danos_visibles' =>
            'Con daños visibles',

        'desgaste_normal' =>
            'Desgaste normal',

        'no_verificado' =>
            'No fue posible verificar',
    ];

    public const POWER_STATUSES = [
        'enciende' =>
            'Enciende',

        'no_enciende' =>
            'No enciende',

        'no_probado' =>
            'No se probó',

        'no_aplica' =>
            'No aplica',
    ];

    public const ACCESSORIES = [
        'ninguno' =>
            'Sin accesorios',

        'cargador' =>
            'Cargador / eliminador',

        'cable' =>
            'Cable(s)',

        'bateria' =>
            'Batería',

        'funda' =>
            'Funda / estuche',

        'memoria' =>
            'Memoria / almacenamiento',

        'control' =>
            'Control / accesorio de mando',

        'otro' =>
            'Otro',
    ];

    public const CONFIRMATIONS = [
        'identidad_verificada' =>
            'Producto, modelo y serie/lote verificados cuando aplica',

        'condicion_revisada' =>
            'Estado físico revisado con el cliente',

        'accesorios_revisados' =>
            'Accesorios entregados quedaron documentados',

        'problema_confirmado' =>
            'Problema reportado por el cliente fue confirmado',
    ];

    public function validateData(array $data): array
    {
        $physicalCondition = (string) (
            $data['reception_physical_condition']
            ?? ''
        );

        if (
            ! array_key_exists(
                $physicalCondition,
                self::PHYSICAL_CONDITIONS
            )
        ) {
            throw ValidationException::withMessages([
                'reception_physical_condition' =>
                    'Selecciona la condición física del producto.',
            ]);
        }

        $powerStatus = (string) (
            $data['reception_power_status']
            ?? ''
        );

        if (
            ! array_key_exists(
                $powerStatus,
                self::POWER_STATUSES
            )
        ) {
            throw ValidationException::withMessages([
                'reception_power_status' =>
                    'Selecciona el resultado de la revisión de encendido.',
            ]);
        }

        $accessories = array_values(
            array_unique(
                array_map(
                    'strval',
                    (array) (
                        $data['reception_accessories']
                        ?? []
                    )
                )
            )
        );

        if ($accessories === []) {
            throw ValidationException::withMessages([
                'reception_accessories' =>
                    'Indica los accesorios recibidos o selecciona Sin accesorios.',
            ]);
        }

        foreach ($accessories as $accessory) {
            if (
                ! array_key_exists(
                    $accessory,
                    self::ACCESSORIES
                )
            ) {
                throw ValidationException::withMessages([
                    'reception_accessories' =>
                        'Existe un accesorio de recepción no válido.',
                ]);
            }
        }

        if (
            in_array(
                'ninguno',
                $accessories,
                true
            )
            && count($accessories) > 1
        ) {
            throw ValidationException::withMessages([
                'reception_accessories' =>
                    'Sin accesorios no puede combinarse con otros accesorios.',
            ]);
        }

        $otherAccessories = trim((string) (
            $data['reception_accessories_other']
            ?? ''
        ));

        if (
            in_array(
                'otro',
                $accessories,
                true
            )
            && $otherAccessories === ''
        ) {
            throw ValidationException::withMessages([
                'reception_accessories_other' =>
                    'Describe el accesorio adicional recibido.',
            ]);
        }

        $confirmations = array_values(
            array_unique(
                array_map(
                    'strval',
                    (array) (
                        $data['reception_confirmations']
                        ?? []
                    )
                )
            )
        );

        $requiredConfirmations =
            array_keys(
                self::CONFIRMATIONS
            );

        $missingConfirmations =
            array_values(
                array_diff(
                    $requiredConfirmations,
                    $confirmations
                )
            );

        if ($missingConfirmations !== []) {
            throw ValidationException::withMessages([
                'reception_confirmations' =>
                    'Confirma todos los puntos obligatorios de recepción.',
            ]);
        }

        $notes = trim((string) (
            $data['reception_notes']
            ?? ''
        ));

        if ($notes === '') {
            throw ValidationException::withMessages([
                'reception_notes' =>
                    'Captura las observaciones de recepción.',
            ]);
        }

        return [
            'physical_condition' =>
                $physicalCondition,

            'power_status' =>
                $powerStatus,

            'accessories' =>
                $accessories,

            'accessories_other' =>
                $otherAccessories,

            'confirmations' =>
                $requiredConfirmations,

            'notes' =>
                $notes,
        ];
    }

    public function recordChecklist(
        RepairOrder $repairOrder,
        array $data
    ): RepairOrder {
        $normalized =
            $this->validateData($data);

        return DB::transaction(
            function () use (
                $repairOrder,
                $normalized
            ): RepairOrder {
                $repair =
                    RepairOrder::query()
                        ->whereKey(
                            $repairOrder->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $metadata =
                    (array) (
                        $repair->metadata
                        ?? []
                    );

                $history =
                    $metadata[
                        'reception_checklist_history'
                    ]
                    ?? [];

                if (! is_array($history)) {
                    $history = [];
                }

                $entry = array_merge(
                    $normalized,
                    [
                        'version' =>
                            1,

                        'recorded_at' =>
                            now()
                                ->toDateTimeString(),

                        'recorded_by' =>
                            auth()->id(),

                        'previous_received_condition' =>
                            $repair
                                ->received_condition,
                    ]
                );

                $history[] = $entry;

                $metadata[
                    'reception_checklist'
                ] = $entry;

                $metadata[
                    'reception_checklist_history'
                ] = $history;

                $receivedCondition =
                    $this->humanSummary(
                        $entry
                    );

                $repair->update([
                    'received_condition' =>
                        $receivedCondition,

                    'metadata' =>
                        $metadata,
                ]);

                $this->logEvent(
                    repair: $repair,
                    entry: $entry
                );

                return $repair->fresh();
            }
        );
    }

    protected function humanSummary(
        array $entry
    ): string {
        $accessories =
            array_map(
                fn (
                    string $value
                ): string =>
                    self::ACCESSORIES[
                        $value
                    ]
                    ?? $value,
                $entry['accessories']
                ?? []
            );

        if (
            in_array(
                'otro',
                $entry['accessories']
                    ?? [],
                true
            )
            && filled(
                $entry[
                    'accessories_other'
                ]
                ?? null
            )
        ) {
            $accessories[] =
                'Detalle adicional: '
                . trim(
                    (string) $entry[
                        'accessories_other'
                    ]
                );
        }

        return implode(
            PHP_EOL,
            [
                'Estado físico: '
                    . (
                        self::PHYSICAL_CONDITIONS[
                            $entry[
                                'physical_condition'
                            ]
                        ]
                        ?? $entry[
                            'physical_condition'
                        ]
                    ),

                'Encendido: '
                    . (
                        self::POWER_STATUSES[
                            $entry[
                                'power_status'
                            ]
                        ]
                        ?? $entry[
                            'power_status'
                        ]
                    ),

                'Accesorios recibidos: '
                    . implode(
                        ', ',
                        $accessories
                    ),

                'Observaciones: '
                    . trim(
                        (string) (
                            $entry['notes']
                            ?? ''
                        )
                    ),
            ]
        );
    }

    protected function logEvent(
        RepairOrder $repair,
        array $entry
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

        $row = [
            'company_id' =>
                $repair->company_id,

            'service_case_id' =>
                $repair->service_case_id,

            'repair_order_id' =>
                $repair->id,

            'event_type' =>
                'reception_checklist_recorded',

            'performed_by' =>
                auth()->id(),

            'performed_at' =>
                $now,

            'notes' =>
                'Checklist obligatorio de recepción registrado.',

            'new_values' =>
                json_encode(
                    [
                        'received_condition' =>
                            $repair
                                ->received_condition,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'metadata' =>
                json_encode(
                    [
                        'source' =>
                            'reception_checklist',

                        'checklist' =>
                            $entry,
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ];

        $safe = array_intersect_key(
            $row,
            array_flip($columns)
        );

        DB::table(
            'service_case_events'
        )->insert($safe);
    }
}
