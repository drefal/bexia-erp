<?php

namespace App\Filament\Resources\RepairOrderResource\Pages;






use App\Filament\Resources\AccountReceivableResource;
use App\Support\Service\ServiceReceivableCreator;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use App\Support\Service\ServiceEconomicClosureCalculator;
use App\Filament\Resources\RepairOrderResource;
use App\Support\Service\ServiceAccess;
use Filament\Resources\Pages\EditRecord;

class EditRepairOrder extends EditRecord
{
    use \App\Support\Service\Concerns\HasRepairQuoteApprovalHeaderActions;

    protected static string $resource = RepairOrderResource::class;

    protected ?string $oldStatus = null;

    protected mixed $oldAssignedEmployeeId = null;

    protected mixed $uploadedAttachments = [];

    /*
     * BEXIA_ATC_TECHNICIAN_NO_GENERAL_SAVE_V5_83_4C5B
     *
     * El técnico no guarda el formulario administrativo.
     * Toda su escritura pasa por una acción específica.
     */
    protected function getFormActions(): array
    {

        if (
            $this->
                isReceptionRepairReadOnlyUser()
        ) {
            return [];
        }


        /*
         * BEXIA_ATC_MANAGER_REVIEW_NO_SAVE_V5_83_4C5C2A1
         *
         * El Encargado usa acciones de etapa.
         * No debe existir Guardar general en esta vista.
         */
        if (
            ServiceAccess::hasServiceRole(
                'Servicio - Encargado de Técnicos'
            )
            && ! ServiceAccess::hasServiceRole(
                'Servicio - Supervisor'
            )
        ) {
            return [];
        }

        if (
            ServiceAccess::isRestrictedServiceTechnician()
        ) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->oldStatus = $this->record->status;
        $this->oldAssignedEmployeeId = $this->record->assigned_employee_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {

        if (
            $this->
                isReceptionRepairReadOnlyUser()
        ) {
            throw
                \Illuminate\Validation\ValidationException::
                    withMessages([
                        'repair' =>
                            'Recepción puede consultar la reparación, pero no modificar sus datos técnicos, presupuesto o costos.',
                    ]);
        }

        /*
         * BEXIA_ATC_TECHNICIAN_GENERAL_SAVE_GUARD_V5_83_4C5B
         *
         * Aunque alguien intente forzar el submit del formulario
         * Livewire, un Servicio - Técnico restringido no puede
         * modificar la orden por el Save administrativo.
         */
        if (
            ServiceAccess::isRestrictedServiceTechnician()
        ) {
            throw new
                \Illuminate\Auth\Access\AuthorizationException(
                    'El técnico sólo puede registrar su trabajo mediante la acción Finalizar trabajo técnico.'
                );
        }

        $this->uploadedAttachments = $data['uploaded_attachments'] ?? [];
        unset($data['uploaded_attachments']);

        if (
            array_key_exists('assigned_employee_id', $data)
            && (string) ($data['assigned_employee_id'] ?? '') !== (string) ($this->oldAssignedEmployeeId ?? '')
            && auth()->check()
        ) {
            $data['assigned_by'] = auth()->id();
            $data['assigned_at'] = now();
        }

        if (($data['status'] ?? null) === 'en_reparacion' && empty($data['started_at'])) {
            $data['started_at'] = now();
        }

        if (($data['status'] ?? null) === 'listo_entrega' && empty($data['finished_at'])) {
            $data['finished_at'] = now();
        }

        if (($data['status'] ?? null) === 'entregado' && empty($data['delivered_at'])) {
            $data['delivered_at'] = now();
        }

        if (($data['status'] ?? null) === 'cerrado' && empty($data['closed_at'])) {
            $data['closed_at'] = now();
        }

        if (empty($data['product_name']) && ! empty($data['product_id'])) {
            $data['product_name'] = ServiceAccess::productLabel((int) $data['product_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        ServiceAccess::saveUploadedAttachments(
            companyId: $this->record->company_id,
            serviceCaseId: $this->record->service_case_id,
            repairOrderId: $this->record->id,
            files: $this->uploadedAttachments,
            stage: 'reparacion'
        );

        if ($this->oldStatus !== $this->record->status) {
            RepairOrderResource::logEvent(
                $this->record,
                'cambio_estado_reparacion',
                $this->oldStatus,
                $this->record->status,
                'Cambio de estado desde Filament.'
            );

            return;
        }

        if ((string) ($this->oldAssignedEmployeeId ?? '') !== (string) ($this->record->assigned_employee_id ?? '')) {
            RepairOrderResource::logEvent(
                $this->record,
                'reasignacion_reparacion',
                $this->record->status,
                $this->record->status,
                'Cambio de tecnico responsable desde Filament.'
            );

            return;
        }

        RepairOrderResource::logEvent(
            $this->record,
            'reparacion_actualizada',
            $this->record->status,
            $this->record->status,
            'Reparacion actualizada desde Filament.'
        );
    }

    /*
     * BEXIA_ATC_TECHNICIAN_WORK_ACTION_V5_83_4C5B
     *
     * Única escritura operativa disponible para el técnico.
     *
     * Guarda:
     * - diagnóstico técnico;
     * - trabajo realizado;
     * - refacciones y cantidades;
     * - pruebas / observaciones;
     * - evidencia.
     *
     * NO guarda:
     * - costo;
     * - precio;
     * - mano de obra económica;
     * - garantía;
     * - cotización;
     * - total al cliente.
     *
     * El workflow_stage se conserva para que el Encargado de
     * Técnicos costee después usando el flujo administrativo.
     *
     * BEXIA_ATC_TECHNICIAN_STRING_CAST_FIX_V5_83_4C5B4
     * Corrige cast runtime de technical_work.status.
     */
    protected function finalizeTechnicalWorkAction(): Action
    {
        return Action::make(
            'finalize_technical_work'
        )
            ->label(
                'Finalizar trabajo técnico'
            )
            ->icon(
                'heroicon-o-wrench-screwdriver'
            )
            ->color('success')
            ->modalHeading(
                'Finalizar trabajo técnico'
            )
            ->modalDescription(
                'Registra qué encontraste, qué hiciste y las refacciones utilizadas. No se capturan costos ni precios; el Encargado de Técnicos realizará después el costeo y cobro.'
            )
            ->modalSubmitActionLabel(
                'Finalizar trabajo técnico'
            )
            ->visible(
                fn (): bool =>
                    ServiceAccess::
                        isRestrictedServiceTechnician()
                    && ServiceAccess::
                        isAssignedRepairTechnician(
                            $this->record
                        )
                    && ServiceAccess::
                        canWorkRepair(
                            $this->record
                        )
                    && ! $this->
                        technicalWorkCompleted()
                    && ! $this->
                        repairIsFinalForTechnician()
            )
            ->form([
                \Filament\Forms\Components\Textarea::make(
                    'technical_diagnosis'
                )
                    ->label('Diagnóstico técnico')
                    ->helperText(
                        'Describe la falla encontrada y el diagnóstico realizado.'
                    )
                    ->default(
                        fn (): ?string =>
                            $this->record
                                ? (
                                    $this->record->
                                        technical_diagnosis
                                    ?: null
                                )
                                : null
                    )
                    ->rows(5)
                    ->required()
                    ->maxLength(5000)
                    ->columnSpanFull(),

                \Filament\Forms\Components\Textarea::make(
                    'work_performed'
                )
                    ->label(
                        'Trabajo realizado'
                    )
                    ->helperText(
                        'Describe con claridad qué se reparó, ajustó, sustituyó o corrigió.'
                    )
                    ->default(
                        fn (): ?string =>
                            $this->record
                                ? (
                                    $this->record->
                                        resolution
                                    ?: null
                                )
                                : null
                    )
                    ->rows(5)
                    ->required()
                    ->maxLength(5000)
                    ->columnSpanFull(),

                \Filament\Forms\Components\Repeater::make(
                    'technical_parts'
                )
                    ->label(
                        'Refacciones / materiales utilizados'
                    )
                    ->helperText(
                        'Registra únicamente refacción/material y cantidad. No se muestran ni capturan costos o precios. Si no utilizaste refacciones, deja esta sección vacía.'
                    )
                    ->defaultItems(0)
                    ->addActionLabel(
                        'Agregar refacción o material'
                    )
                    ->collapsible()
                    ->columns(12)
                    ->schema([
                        \Filament\Forms\Components\Select::make(
                            'source_type'
                        )
                            ->label('Origen')
                            ->options([
                                'catalog' =>
                                    'Catálogo / almacén',
                                'manual' =>
                                    'Captura manual',
                            ])
                            ->default('catalog')
                            ->native(false)
                            ->required()
                            ->live()
                            ->columnSpan(3),

                        \Filament\Forms\Components\Select::make(
                            'product_id'
                        )
                            ->label(
                                'Refacción / material'
                            )
                            ->searchable()
                            ->preload()
                            ->options(
                                fn (): array =>
                                    ServiceAccess::
                                        productOptions()
                            )
                            ->getSearchResultsUsing(
                                fn (
                                    string $search
                                ): array =>
                                    ServiceAccess::
                                        productOptions(
                                            $search
                                        )
                            )
                            ->getOptionLabelUsing(
                                fn (
                                    $value
                                ): ?string =>
                                    $value
                                        ? ServiceAccess::
                                            productLabel(
                                                (int)
                                                $value
                                            )
                                        : null
                            )
                            ->required(
                                fn (
                                    \Filament\Forms\Get
                                    $get
                                ): bool =>
                                    (
                                        $get(
                                            'source_type'
                                        )
                                        ?: 'catalog'
                                    ) === 'catalog'
                            )
                            ->visible(
                                fn (
                                    \Filament\Forms\Get
                                    $get
                                ): bool =>
                                    (
                                        $get(
                                            'source_type'
                                        )
                                        ?: 'catalog'
                                    ) === 'catalog'
                            )
                            ->columnSpan(5),

                        \Filament\Forms\Components\TextInput::make(
                            'product_name'
                        )
                            ->label(
                                'Refacción / material'
                            )
                            ->helperText(
                                'Úsalo sólo cuando la refacción no exista en catálogo.'
                            )
                            ->required(
                                fn (
                                    \Filament\Forms\Get
                                    $get
                                ): bool =>
                                    $get(
                                        'source_type'
                                    ) === 'manual'
                            )
                            ->visible(
                                fn (
                                    \Filament\Forms\Get
                                    $get
                                ): bool =>
                                    $get(
                                        'source_type'
                                    ) === 'manual'
                            )
                            ->maxLength(255)
                            ->columnSpan(5),

                        \Filament\Forms\Components\TextInput::make(
                            'quantity'
                        )
                            ->label('Cantidad')
                            ->numeric()
                            ->default(1)
                            ->minValue(0.01)
                            ->step('0.01')
                            ->required()
                            ->columnSpan(2),

                        \Filament\Forms\Components\Textarea::make(
                            'notes'
                        )
                            ->label(
                                'Observaciones'
                            )
                            ->helperText(
                                'Opcional: ubicación, lado, medida o cualquier detalle útil.'
                            )
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                \Filament\Forms\Components\Textarea::make(
                    'tests_notes'
                )
                    ->label(
                        'Pruebas / observaciones finales'
                    )
                    ->helperText(
                        'Opcional. Indica las pruebas realizadas o cualquier observación después de la reparación.'
                    )
                    ->rows(3)
                    ->maxLength(3000)
                    ->columnSpanFull(),

                \Filament\Forms\Components\FileUpload::make(
                    'technical_files'
                )
                    ->label(
                        'Evidencia del trabajo'
                    )
                    ->helperText(
                        'Obligatorio: agrega al menos una foto o documento que muestre el trabajo realizado.'
                    )
                    ->required()
                    ->minFiles(1)
                    ->validationMessages([
                        'required' =>
                            'Agrega al menos una evidencia del trabajo técnico.',
                        'min' =>
                            'Agrega al menos una evidencia del trabajo técnico.',
                    ])
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                        'application/pdf',
                        'text/plain',
                        'text/csv',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->disk('public')
                    ->directory(
                        'service/repair-result-files'
                    )
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->imagePreviewHeight('120')
                    ->maxFiles(10)
                    ->maxSize(10240)
                    ->columnSpanFull(),
            ])
            ->action(
                function (
                    array $data
                ): void {
                    $record =
                        $this->record;

                    if (
                        ! $record
                        || ! ServiceAccess::
                            isRestrictedServiceTechnician()
                        || ! ServiceAccess::
                            isAssignedRepairTechnician(
                                $record
                            )
                        || ! ServiceAccess::
                            canWorkRepair(
                                $record
                            )
                    ) {
                        throw new
                            \Illuminate\Auth\Access\AuthorizationException(
                                'No tienes autorización para finalizar esta reparación.'
                            );
                    }

                    if (
                        $this->
                            repairIsFinalForTechnician()
                    ) {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'technical_diagnosis' =>
                                        'La reparación ya está en un estado final y no puede modificarse.',
                                ]);
                    }

                    $diagnosis =
                        trim(
                            (string) (
                                $data[
                                    'technical_diagnosis'
                                ]
                                ?? ''
                            )
                        );

                    $workPerformed =
                        trim(
                            (string) (
                                $data[
                                    'work_performed'
                                ]
                                ?? ''
                            )
                        );

                    $testsNotes =
                        trim(
                            (string) (
                                $data[
                                    'tests_notes'
                                ]
                                ?? ''
                            )
                        );

                    $parts =
                        array_values(
                            array_filter(
                                (array) (
                                    $data[
                                        'technical_parts'
                                    ]
                                    ?? []
                                ),
                                fn ($part): bool =>
                                    is_array($part)
                            )
                        );

                    $paths =
                        array_values(
                            array_filter(
                                (array) (
                                    $data[
                                        'technical_files'
                                    ]
                                    ?? []
                                ),
                                fn ($path): bool =>
                                    is_string($path)
                                    && trim($path) !== ''
                            )
                        );

                    if ($diagnosis === '') {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'technical_diagnosis' =>
                                        'Captura el diagnóstico técnico.',
                                ]);
                    }

                    if ($workPerformed === '') {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'work_performed' =>
                                        'Captura el trabajo realizado.',
                                ]);
                    }

                    if ($paths === []) {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'technical_files' =>
                                        'Agrega al menos una evidencia del trabajo técnico.',
                                ]);
                    }

                    \Illuminate\Support\Facades\DB::
                        transaction(
                            function () use (
                                $record,
                                $diagnosis,
                                $workPerformed,
                                $testsNotes,
                                $parts,
                                $paths
                            ): void {
                                $repair =
                                    \App\Models\RepairOrder::
                                        query()
                                        ->lockForUpdate()
                                        ->find(
                                            $record->
                                                getKey()
                                        );

                                if (! $repair) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'technical_diagnosis' =>
                                                    'La orden técnica ya no está disponible.',
                                            ]);
                                }

                                if (
                                    ! ServiceAccess::
                                        isAssignedRepairTechnician(
                                            $repair
                                        )
                                    || ! ServiceAccess::
                                        canWorkRepair(
                                            $repair
                                        )
                                ) {
                                    throw new
                                        \Illuminate\Auth\Access\AuthorizationException(
                                            'Ya no tienes asignada esta reparación.'
                                        );
                                }

                                $metadata =
                                    $repair->metadata
                                    ?? [];

                                if (
                                    is_string(
                                        $metadata
                                    )
                                ) {
                                    $decoded =
                                        json_decode(
                                            $metadata,
                                            true
                                        );

                                    $metadata =
                                        is_array(
                                            $decoded
                                        )
                                            ? $decoded
                                            : [];
                                }

                                if (
                                    ! is_array(
                                        $metadata
                                    )
                                ) {
                                    $metadata = [];
                                }

                                $existingWork =
                                    $metadata[
                                        'technical_work'
                                    ]
                                    ?? [];

                                if (
                                    is_array(
                                        $existingWork
                                    )
                                    && (string) (
                                        $existingWork[
                                            'status'
                                        ]
                                        ?? ''
                                    ) === 'completed'
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'technical_diagnosis' =>
                                                    'El trabajo técnico ya fue finalizado anteriormente.',
                                            ]);
                                }

                                /*
                                 * No se reemplazan líneas que ya existan.
                                 * Si alguien administrativo ya capturó
                                 * refacciones, se detiene para evitar
                                 * borrar/cambiar costeo o inventario.
                                 */
                                $existingPartsCount =
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_order_parts'
                                        )
                                        ->where(
                                            'repair_order_id',
                                            $repair->id
                                        )
                                        ->count();

                                if (
                                    $existingPartsCount > 0
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'technical_parts' =>
                                                    'Esta orden ya tiene refacciones registradas. El Encargado de Técnicos debe revisarlas antes de finalizar el trabajo.',
                                            ]);
                                }

                                $now = now();

                                $partSummaries = [];

                                foreach (
                                    $parts
                                    as $index => $part
                                ) {
                                    $sourceType =
                                        (string) (
                                            $part[
                                                'source_type'
                                            ]
                                            ?? 'catalog'
                                        );

                                    if (
                                        ! in_array(
                                            $sourceType,
                                            [
                                                'catalog',
                                                'manual',
                                            ],
                                            true
                                        )
                                    ) {
                                        throw
                                            \Illuminate\Validation\ValidationException::
                                                withMessages([
                                                    'technical_parts' =>
                                                        'Origen de refacción inválido en la línea '
                                                        . (
                                                            $index
                                                            + 1
                                                        )
                                                        . '.',
                                                ]);
                                    }

                                    $quantity =
                                        (float) (
                                            $part[
                                                'quantity'
                                            ]
                                            ?? 0
                                        );

                                    if (
                                        $quantity <= 0
                                    ) {
                                        throw
                                            \Illuminate\Validation\ValidationException::
                                                withMessages([
                                                    'technical_parts' =>
                                                        'La cantidad debe ser mayor que cero en la línea '
                                                        . (
                                                            $index
                                                            + 1
                                                        )
                                                        . '.',
                                                ]);
                                    }

                                    $productId = null;
                                    $productName = '';
                                    $sku = null;

                                    if (
                                        $sourceType
                                        === 'catalog'
                                    ) {
                                        $productId =
                                            (int) (
                                                $part[
                                                    'product_id'
                                                ]
                                                ?? 0
                                            );

                                        if (
                                            $productId <= 0
                                        ) {
                                            throw
                                                \Illuminate\Validation\ValidationException::
                                                    withMessages([
                                                        'technical_parts' =>
                                                            'Selecciona la refacción de catálogo en la línea '
                                                            . (
                                                                $index
                                                                + 1
                                                            )
                                                            . '.',
                                                    ]);
                                        }

                                        $productQuery =
                                            \Illuminate\Support\Facades\DB::
                                                table(
                                                    'products'
                                                )
                                                ->where(
                                                    'id',
                                                    $productId
                                                );

                                        if (
                                            \Illuminate\Support\Facades\Schema::
                                                hasColumn(
                                                    'products',
                                                    'company_id'
                                                )
                                        ) {
                                            $companyId =
                                                (int) (
                                                    $repair->
                                                        company_id
                                                    ?? 0
                                                );

                                            $productQuery->
                                                where(
                                                    function (
                                                        $query
                                                    ) use (
                                                        $companyId
                                                    ): void {
                                                        $query->
                                                            where(
                                                                'company_id',
                                                                $companyId
                                                            )
                                                            ->orWhereNull(
                                                                'company_id'
                                                            );
                                                    }
                                                );
                                        }

                                        $product =
                                            $productQuery->
                                                first();

                                        if (
                                            ! $product
                                        ) {
                                            throw
                                                \Illuminate\Validation\ValidationException::
                                                    withMessages([
                                                        'technical_parts' =>
                                                            'La refacción seleccionada no pertenece al catálogo disponible de esta empresa.',
                                                    ]);
                                        }

                                        $productName =
                                            trim(
                                                (string) (
                                                    ServiceAccess::
                                                        productLabel(
                                                            $productId
                                                        )
                                                    ?? ''
                                                )
                                            );

                                        if (
                                            property_exists(
                                                $product,
                                                'sku'
                                            )
                                            && filled(
                                                $product->sku
                                            )
                                        ) {
                                            $sku =
                                                mb_substr(
                                                    trim(
                                                        (string)
                                                        $product->sku
                                                    ),
                                                    0,
                                                    255
                                                );
                                        }
                                    } else {
                                        $productName =
                                            trim(
                                                (string) (
                                                    $part[
                                                        'product_name'
                                                    ]
                                                    ?? ''
                                                )
                                            );

                                        if (
                                            $productName === ''
                                        ) {
                                            throw
                                                \Illuminate\Validation\ValidationException::
                                                    withMessages([
                                                        'technical_parts' =>
                                                            'Captura la refacción o material manual en la línea '
                                                            . (
                                                                $index
                                                                + 1
                                                            )
                                                            . '.',
                                                    ]);
                                        }
                                    }

                                    if (
                                        $productName === ''
                                    ) {
                                        $productName =
                                            'Refacción';
                                    }

                                    $productName =
                                        mb_substr(
                                            $productName,
                                            0,
                                            255
                                        );

                                    $notes =
                                        trim(
                                            (string) (
                                                $part[
                                                    'notes'
                                                ]
                                                ?? ''
                                            )
                                        );

                                    /*
                                     * IMPORTANTE:
                                     * El técnico NO captura costo/precio.
                                     *
                                     * unit_cost / total_cost se guardan
                                     * en 0 porque la tabla los exige.
                                     *
                                     * unit_price / total_price quedan
                                     * NULL para que el Encargado los
                                     * complete posteriormente.
                                     */
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_order_parts'
                                        )
                                        ->insert([
                                            'company_id' =>
                                                $repair->
                                                    company_id,

                                            'repair_order_id' =>
                                                $repair->id,

                                            'product_id' =>
                                                $productId,

                                            'sku' =>
                                                $sku,

                                            'description' =>
                                                $productName,

                                            'quantity' =>
                                                $quantity,

                                            'unit_cost' =>
                                                0,

                                            'total_cost' =>
                                                0,

                                            'notes' =>
                                                $notes !== ''
                                                    ? $notes
                                                    : null,

                                            'source_type' =>
                                                $sourceType,

                                            'product_name' =>
                                                $productName,

                                            'unit_price' =>
                                                null,

                                            'total_price' =>
                                                null,

                                            'created_at' =>
                                                $now,

                                            'updated_at' =>
                                                $now,
                                        ]);

                                    $partSummaries[] = [
                                        'source_type' =>
                                            $sourceType,

                                        'product_id' =>
                                            $productId,

                                        'product_name' =>
                                            $productName,

                                        'quantity' =>
                                            $quantity,

                                        'notes' =>
                                            $notes !== ''
                                                ? $notes
                                                : null,
                                    ];
                                }

                                $metadata[
                                    'technical_work'
                                ] = [
                                    'status' =>
                                        'completed',

                                    'technical_diagnosis' =>
                                        $diagnosis,

                                    'work_performed' =>
                                        $workPerformed,

                                    'tests_notes' =>
                                        $testsNotes !== ''
                                            ? $testsNotes
                                            : null,

                                    'parts' =>
                                        $partSummaries,

                                    'parts_count' =>
                                        count(
                                            $partSummaries
                                        ),

                                    'evidence_count' =>
                                        count(
                                            $paths
                                        ),

                                    'completed_at' =>
                                        $now->
                                            toDateTimeString(),

                                    'completed_by_user_id' =>
                                        auth()->id(),

                                    'assigned_employee_id' =>
                                        $repair->
                                            assigned_employee_id,

                                    'workflow_stage_preserved' =>
                                        $repair->
                                            workflow_stage,

                                    'source' =>
                                        'technician_finalize_action',
                                ];

                                /*
                                 * Se conservan workflow_stage,
                                 * status, garantía y cotización.
                                 *
                                 * Así el Encargado de Técnicos
                                 * puede hacer el costeo después.
                                 */
                                \Illuminate\Support\Facades\DB::
                                    table(
                                        'repair_orders'
                                    )
                                    ->where(
                                        'id',
                                        $repair->id
                                    )
                                    ->update([
                                        'technical_diagnosis' =>
                                            $diagnosis,

                                        'resolution' =>
                                            $workPerformed,

                                        'metadata' =>
                                            json_encode(
                                                $metadata,
                                                JSON_UNESCAPED_UNICODE
                                                | JSON_UNESCAPED_SLASHES
                                                | JSON_THROW_ON_ERROR
                                            ),

                                        'updated_at' =>
                                            $now,
                                    ]);

                                ServiceAccess::
                                    saveUploadedAttachments(
                                        companyId:
                                            $repair->
                                                company_id,

                                        serviceCaseId:
                                            $repair->
                                                service_case_id,

                                        repairOrderId:
                                            $repair->id,

                                        files:
                                            $paths,

                                        stage:
                                            'repair_result',

                                        isCustomerVisible:
                                            false
                                    );

                                foreach (
                                    $paths
                                    as $path
                                ) {
                                    $attachmentExists =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'service_attachments'
                                            )
                                            ->where(
                                                'repair_order_id',
                                                $repair->id
                                            )
                                            ->where(
                                                'stage',
                                                'repair_result'
                                            )
                                            ->where(
                                                'file_path',
                                                $path
                                            )
                                            ->exists();

                                    if (
                                        ! $attachmentExists
                                    ) {
                                        throw
                                            \Illuminate\Validation\ValidationException::
                                                withMessages([
                                                    'technical_files' =>
                                                        'No fue posible registrar toda la evidencia del trabajo técnico.',
                                                ]);
                                    }
                                }

                                $repair->refresh();

                                RepairOrderResource::
                                    logEvent(
                                        $repair,
                                        'technical_work_completed',
                                        (string) (
                                            $repair->status
                                            ?? ''
                                        ),
                                        (string) (
                                            $repair->status
                                            ?? ''
                                        ),
                                        'El técnico finalizó su trabajo. Diagnóstico, trabajo realizado, refacciones y evidencia quedaron registrados. Pendiente de revisión y costeo por el Encargado de Técnicos.'
                                    );
                            }
                        );

                    $this->record->
                        refresh();

                    \Filament\Notifications\Notification::
                        make()
                        ->title(
                            'Trabajo técnico finalizado'
                        )
                        ->body(
                            'El trabajo quedó bloqueado para el técnico. El siguiente paso corresponde al Encargado de Técnicos para revisión, costeo y cobro.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        $this->
                            getResource()::
                            getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $this->
                                            record,
                                ]
                            )
                    );
                }
            );
    }

    protected function technicalWorkMetadata(): array
    {
        $record =
            $this->record
            ?? null;

        if (! $record) {
            return [];
        }

        $metadata =
            $record->metadata
            ?? [];

        if (is_string($metadata)) {
            $decoded =
                json_decode(
                    $metadata,
                    true
                );

            $metadata =
                is_array($decoded)
                    ? $decoded
                    : [];
        }

        if (! is_array($metadata)) {
            return [];
        }

        $work =
            $metadata[
                'technical_work'
            ]
            ?? [];

        return
            is_array($work)
                ? $work
                : [];
    }

    protected function technicalWorkCompleted(): bool
    {
        $work =
            $this->
                technicalWorkMetadata();

        return
            (string) (
                $work['status']
                ?? ''
            ) === 'completed';
    }

    protected function repairIsFinalForTechnician(): bool
    {
        $record =
            $this->record
            ?? null;

        if (! $record) {
            return true;
        }

        return
            in_array(
                (string) (
                    $record->
                        workflow_stage
                    ?? ''
                ),
                [
                    'ready_for_delivery',
                    'delivered',
                    'cancelled',
                    'finished',
                ],
                true
            )
            || in_array(
                (string) (
                    $record->status
                    ?? ''
                ),
                [
                    'ready_for_delivery',
                    'delivered',
                    'entregado',
                    'cerrado',
                    'rechazado',
                    'cancelled',
                    'cancelado',
                ],
                true
            );
    }


    /*
     * BEXIA_ATC_MANAGER_COST_ACTION_V5_83_4C5C2B
     *
     * Unica accion economica operativa del
     * Encargado de Tecnicos despues de que el
     * tecnico finaliza el trabajo.
     *
     * NO permite cambiar:
     * - diagnostico;
     * - trabajo realizado;
     * - refaccion;
     * - cantidad tecnica;
     * - producto / serie;
     * - tecnico asignado.
     *
     * Si permite:
     * - decision comercial;
     * - costo de refaccion;
     * - precio de venta;
     * - mano de obra;
     * - otros cargos;
     * - Vo.Bo. del cliente;
     * - notas.
     */
    protected function managerReviewCostingAction(): Action
    {
        return Action::make(
            'manager_review_costing'
        )
            ->label(
                'Revisar y costear'
            )
            ->icon(
                'heroicon-o-calculator'
            )
            ->color('success')
            ->modalHeading(
                'Revisar y costear'
            )
            ->modalDescription(
                'Revisa el trabajo técnico y registra '
                . 'únicamente la decisión económica. '
                . 'El diagnóstico, trabajo realizado, '
                . 'refacciones y cantidades técnicas '
                . 'permanecen bloqueados.'
            )
            ->modalWidth('6xl')
            ->modalSubmitActionLabel(
                'Guardar revisión y costeo'
            )
            ->visible(
                fn (): bool =>
                    $this->
                        managerReviewCostingPending()
            )
            ->form([
                \Filament\Forms\Components\Radio::make(
                    'decision'
                )
                    ->label(
                        'Decisión'
                    )
                    ->options([
                        'cobrable' =>
                            'Servicio cobrable / no garantía',

                        'garantia' =>
                            'Garantía aceptada / sin cargo',

                        'garantia_rechazada' =>
                            'Garantía rechazada / cobrable',

                        'cortesia' =>
                            'Cortesía / sin cargo',
                    ])
                    ->default(
                        'cobrable'
                    )
                    ->live()
                    ->required(),

                \Filament\Forms\Components\Repeater::make(
                    'parts'
                )
                    ->label(
                        'Refacciones / materiales '
                        . 'reportados por el técnico'
                    )
                    ->helperText(
                        'La refacción y su cantidad están '
                        . 'bloqueadas. El Encargado captura '
                        . 'únicamente costo y precio.'
                    )
                    ->default(
                        function (): array {
                            if (
                                ! $this->record
                            ) {
                                return [];
                            }

                            return
                                \Illuminate\Support\Facades\DB::
                                    table(
                                        'repair_order_parts'
                                    )
                                    ->where(
                                        'repair_order_id',
                                        $this->record
                                            ->getKey()
                                    )
                                    ->orderBy('id')
                                    ->get()
                                    ->map(
                                        function (
                                            $row
                                        ): array {
                                            return [
                                                'part_id' =>
                                                    (int) $row->id,

                                                'part_name' =>
                                                    trim(
                                                        (string) (
                                                            $row->
                                                                product_name
                                                            ?: $row->
                                                                description
                                                            ?: (
                                                                'Refacción #'
                                                                . $row->id
                                                            )
                                                        )
                                                    ),

                                                'quantity' =>
                                                    (float) (
                                                        $row->
                                                            quantity
                                                        ?? 0
                                                    ),

                                                'unit_cost' =>
                                                    (float) (
                                                        $row->
                                                            unit_cost
                                                        ?? 0
                                                    ),

                                                'unit_price' =>
                                                    $row->
                                                        unit_price
                                                    !== null
                                                        ? (float)
                                                            $row->
                                                                unit_price
                                                        : 0,
                                            ];
                                        }
                                    )
                                    ->all();
                        }
                    )
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(12)
                    ->schema([
                        \Filament\Forms\Components\Hidden::make(
                            'part_id'
                        )
                            ->required(),

                        \Filament\Forms\Components\TextInput::make(
                            'part_name'
                        )
                            ->label(
                                'Refacción / material'
                            )
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(4),

                        \Filament\Forms\Components\TextInput::make(
                            'quantity'
                        )
                            ->label(
                                'Cantidad técnica'
                            )
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(2),

                        \Filament\Forms\Components\TextInput::make(
                            'unit_cost'
                        )
                            ->label(
                                'Costo unitario'
                            )
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->default(0)
                            ->required()
                            ->columnSpan(3),

                        \Filament\Forms\Components\TextInput::make(
                            'unit_price'
                        )
                            ->label(
                                'Precio venta unitario'
                            )
                            ->prefix('$')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->default(0)
                            ->live(
                                debounce: 300
                            )
                            ->helperText(
                                'En garantía o cortesía '
                                . 'el backend lo dejará en $0.'
                            )
                            ->required()
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),

                \Filament\Forms\Components\TextInput::make(
                    'labor_amount'
                )
                    ->label(
                        'Mano de obra a cobrar'
                    )
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->default(0)
                    ->live(
                        debounce: 300
                    )
                    ->helperText(
                        'Importe de mano de obra de esta '
                        . 'reparación. No es el costo '
                        . 'interno por hora.'
                    ),

                \Filament\Forms\Components\TextInput::make(
                    'other_amount'
                )
                    ->label(
                        'Otros cargos'
                    )
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->default(0)
                    ->live(
                        debounce: 300
                    ),

                \Filament\Forms\Components\Placeholder::make(
                    'calculated_customer_total'
                )
                    ->label(
                        'Total al cliente'
                    )
                    ->content(
                        function (
                            \Filament\Forms\Get $get
                        ): string {
                            $decision =
                                (string) (
                                    $get(
                                        'decision'
                                    )
                                    ?? ''
                                );

                            $chargeable =
                                in_array(
                                    $decision,
                                    [
                                        'cobrable',
                                        'garantia_rechazada',
                                    ],
                                    true
                                );

                            if (! $chargeable) {
                                return
                                    '$0.00 · Sin cargo';
                            }

                            $total = 0.0;

                            foreach (
                                (array) (
                                    $get('parts')
                                    ?? []
                                )
                                as $part
                            ) {
                                $qty =
                                    (float) (
                                        $part[
                                            'quantity'
                                        ]
                                        ?? 0
                                    );

                                $price =
                                    (float) (
                                        $part[
                                            'unit_price'
                                        ]
                                        ?? 0
                                    );

                                $total +=
                                    $qty
                                    * $price;
                            }

                            $total +=
                                (float) (
                                    $get(
                                        'labor_amount'
                                    )
                                    ?? 0
                                );

                            $total +=
                                (float) (
                                    $get(
                                        'other_amount'
                                    )
                                    ?? 0
                                );

                            return
                                '$'
                                . number_format(
                                    $total,
                                    2
                                );
                        }
                    ),

                \Filament\Forms\Components\Toggle::make(
                    'requires_customer_approval'
                )
                    ->label(
                        'Requiere Vo.Bo. del cliente'
                    )
                    ->default(true)
                    ->helperText(
                        'Actívalo cuando el importe deba '
                        . 'ser aceptado por el cliente antes '
                        . 'de continuar.'
                    )
                    ->visible(
                        fn (
                            \Filament\Forms\Get
                            $get
                        ): bool =>
                            in_array(
                                (string) (
                                    $get(
                                        'decision'
                                    )
                                    ?? ''
                                ),
                                [
                                    'cobrable',
                                    'garantia_rechazada',
                                ],
                                true
                            )
                    ),

                \Filament\Forms\Components\Textarea::make(
                    'manager_notes'
                )
                    ->label(
                        'Observaciones del Encargado'
                    )
                    ->helperText(
                        'Explica la decisión, costeo '
                        . 'o cualquier consideración '
                        . 'para el siguiente paso.'
                    )
                    ->rows(4)
                    ->maxLength(3000)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(
                function (
                    array $data
                ): void {
                    if (
                        ! \App\Support\Service\ServiceAccess::
                            hasServiceRole(
                                'Servicio - Encargado de Técnicos'
                            )
                        || \App\Support\Service\ServiceAccess::
                            hasServiceRole(
                                'Servicio - Supervisor'
                            )
                    ) {
                        throw new
                            \Illuminate\Auth\Access\AuthorizationException(
                                'Sólo el Encargado de Técnicos '
                                . 'puede registrar esta revisión.'
                            );
                    }

                    $repairId =
                        (int) (
                            $this->record
                                ?->getKey()
                            ?? 0
                        );

                    if ($repairId <= 0) {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'decision' =>
                                        'No se encontró la reparación.',
                                ]);
                    }

                    \Illuminate\Support\Facades\DB::
                        transaction(
                            function () use (
                                $repairId,
                                $data
                            ): void {
                                $repair =
                                    \App\Models\RepairOrder::
                                        query()
                                        ->whereKey(
                                            $repairId
                                        )
                                        ->lockForUpdate()
                                        ->firstOrFail();

                                $metadata =
                                    $repair->metadata
                                    ?? [];

                                if (
                                    is_string(
                                        $metadata
                                    )
                                ) {
                                    $metadata =
                                        json_decode(
                                            $metadata,
                                            true
                                        )
                                        ?: [];
                                }

                                if (
                                    ! is_array(
                                        $metadata
                                    )
                                ) {
                                    $metadata = [];
                                }

                                $technical =
                                    $metadata[
                                        'technical_work'
                                    ]
                                    ?? [];

                                if (
                                    ! is_array(
                                        $technical
                                    )
                                    || (
                                        $technical[
                                            'status'
                                        ]
                                        ?? null
                                    ) !== 'completed'
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'decision' =>
                                                    'El técnico todavía no '
                                                    . 'ha finalizado '
                                                    . 'el trabajo.',
                                            ]);
                                }

                                $existingReview =
                                    $metadata[
                                        'manager_review'
                                    ]
                                    ?? [];

                                if (
                                    is_array(
                                        $existingReview
                                    )
                                    && (
                                        $existingReview[
                                            'status'
                                        ]
                                        ?? null
                                    ) === 'completed'
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'decision' =>
                                                    'La revisión y costeo '
                                                    . 'ya fueron '
                                                    . 'registrados.',
                                            ]);
                                }

                                if (
                                    (string) (
                                        $repair->
                                            workflow_stage
                                        ?? ''
                                    ) !== 'quote_draft'
                                    || (string) (
                                        $repair->status
                                        ?? ''
                                    ) !== 'recibido'
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'decision' =>
                                                    'La reparación cambió '
                                                    . 'de etapa. Recarga '
                                                    . 'antes de continuar.',
                                            ]);
                                }

                                $decision =
                                    (string) (
                                        $data[
                                            'decision'
                                        ]
                                        ?? ''
                                    );

                                $allowed = [
                                    'cobrable',
                                    'garantia',
                                    'garantia_rechazada',
                                    'cortesia',
                                ];

                                if (
                                    ! in_array(
                                        $decision,
                                        $allowed,
                                        true
                                    )
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'decision' =>
                                                    'Selecciona una '
                                                    . 'decisión válida.',
                                            ]);
                                }

                                $chargeable =
                                    in_array(
                                        $decision,
                                        [
                                            'cobrable',
                                            'garantia_rechazada',
                                        ],
                                        true
                                    );

                                $dbParts =
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_order_parts'
                                        )
                                        ->where(
                                            'repair_order_id',
                                            $repairId
                                        )
                                        ->orderBy('id')
                                        ->lockForUpdate()
                                        ->get();

                                $submitted =
                                    collect(
                                        (array) (
                                            $data[
                                                'parts'
                                            ]
                                            ?? []
                                        )
                                    )
                                    ->keyBy(
                                        fn (
                                            array $row
                                        ): int =>
                                            (int) (
                                                $row[
                                                    'part_id'
                                                ]
                                                ?? 0
                                            )
                                    );

                                $dbIds =
                                    $dbParts
                                        ->pluck('id')
                                        ->map(
                                            fn (
                                                $id
                                            ): int =>
                                                (int) $id
                                        )
                                        ->sort()
                                        ->values()
                                        ->all();

                                $submittedIds =
                                    $submitted
                                        ->keys()
                                        ->map(
                                            fn (
                                                $id
                                            ): int =>
                                                (int) $id
                                        )
                                        ->filter(
                                            fn (
                                                int $id
                                            ): bool =>
                                                $id > 0
                                        )
                                        ->sort()
                                        ->values()
                                        ->all();

                                if (
                                    $dbIds
                                    !== $submittedIds
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'parts' =>
                                                    'Las refacciones '
                                                    . 'cambiaron desde '
                                                    . 'que abriste el '
                                                    . 'modal. Recarga '
                                                    . 'la página.',
                                            ]);
                                }

                                $partsCostTotal =
                                    0.0;

                                $partsSaleTotal =
                                    0.0;

                                $partSummary = [];

                                foreach (
                                    $dbParts
                                    as $part
                                ) {
                                    $partId =
                                        (int) $part->id;

                                    $input =
                                        (array) (
                                            $submitted[
                                                $partId
                                            ]
                                            ?? []
                                        );

                                    $quantity =
                                        (float) (
                                            $part->
                                                quantity
                                            ?? 0
                                        );

                                    $unitCost =
                                        round(
                                            max(
                                                0,
                                                (float) (
                                                    $input[
                                                        'unit_cost'
                                                    ]
                                                    ?? 0
                                                )
                                            ),
                                            2
                                        );

                                    $unitPrice =
                                        $chargeable
                                            ? round(
                                                max(
                                                    0,
                                                    (float) (
                                                        $input[
                                                            'unit_price'
                                                        ]
                                                        ?? 0
                                                    )
                                                ),
                                                2
                                            )
                                            : 0.0;

                                    $totalCost =
                                        round(
                                            $quantity
                                            * $unitCost,
                                            2
                                        );

                                    $totalPrice =
                                        round(
                                            $quantity
                                            * $unitPrice,
                                            2
                                        );

                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_order_parts'
                                        )
                                        ->where(
                                            'id',
                                            $partId
                                        )
                                        ->where(
                                            'repair_order_id',
                                            $repairId
                                        )
                                        ->update([
                                            'unit_cost' =>
                                                $unitCost,

                                            'total_cost' =>
                                                $totalCost,

                                            'unit_price' =>
                                                $unitPrice,

                                            'total_price' =>
                                                $totalPrice,

                                            'updated_at' =>
                                                now(),
                                        ]);

                                    $partsCostTotal +=
                                        $totalCost;

                                    $partsSaleTotal +=
                                        $totalPrice;

                                    $partSummary[] = [
                                        'part_id' =>
                                            $partId,

                                        'product_name' =>
                                            (string) (
                                                $part->
                                                    product_name
                                                ?: $part->
                                                    description
                                                ?: (
                                                    'Refacción #'
                                                    . $partId
                                                )
                                            ),

                                        'quantity' =>
                                            $quantity,

                                        'unit_cost' =>
                                            $unitCost,

                                        'total_cost' =>
                                            $totalCost,

                                        'unit_price' =>
                                            $unitPrice,

                                        'total_price' =>
                                            $totalPrice,
                                    ];
                                }

                                $labor =
                                    $chargeable
                                        ? round(
                                            max(
                                                0,
                                                (float) (
                                                    $data[
                                                        'labor_amount'
                                                    ]
                                                    ?? 0
                                                )
                                            ),
                                            2
                                        )
                                        : 0.0;

                                $other =
                                    $chargeable
                                        ? round(
                                            max(
                                                0,
                                                (float) (
                                                    $data[
                                                        'other_amount'
                                                    ]
                                                    ?? 0
                                                )
                                            ),
                                            2
                                        )
                                        : 0.0;

                                $quoteTotal =
                                    $chargeable
                                        ? round(
                                            $partsSaleTotal
                                            + $labor
                                            + $other,
                                            2
                                        )
                                        : 0.0;

                                if (
                                    $chargeable
                                    && $quoteTotal <= 0
                                ) {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'labor_amount' =>
                                                    'Un servicio cobrable '
                                                    . 'debe tener un total '
                                                    . 'mayor a $0.00.',
                                            ]);
                                }

                                $requiresCustomerApproval =
                                    $chargeable
                                    && (bool) (
                                        $data[
                                            'requires_customer_approval'
                                        ]
                                        ?? false
                                    );

                                $warrantyStatus =
                                    match (
                                        $decision
                                    ) {
                                        'garantia' =>
                                            'aceptada',

                                        'garantia_rechazada' =>
                                            'rechazada',

                                        'cobrable',
                                        'cortesia' =>
                                            'no_aplica',
                                    };

                                $quoteStatus =
                                    $requiresCustomerApproval
                                        ? 'pending_customer'
                                        : 'not_required';

                                $notes =
                                    trim(
                                        (string) (
                                            $data[
                                                'manager_notes'
                                            ]
                                            ?? ''
                                        )
                                    );

                                if ($notes === '') {
                                    throw
                                        \Illuminate\Validation\ValidationException::
                                            withMessages([
                                                'manager_notes' =>
                                                    'Captura las '
                                                    . 'observaciones '
                                                    . 'del Encargado.',
                                            ]);
                                }

                                $oldValues = [
                                    'warranty_status' =>
                                        $repair->
                                            warranty_status,

                                    'parts_cost_estimate' =>
                                        $repair->
                                            parts_cost_estimate,

                                    'labor_cost_estimate' =>
                                        $repair->
                                            labor_cost_estimate,

                                    'other_cost_estimate' =>
                                        $repair->
                                            other_cost_estimate,

                                    'quote_total' =>
                                        $repair->
                                            quote_total,

                                    'requires_customer_approval' =>
                                        $repair->
                                            requires_customer_approval,

                                    'quote_status' =>
                                        $repair->
                                            quote_status,
                                ];

                                $managerReview = [
                                    'status' =>
                                        'completed',

                                    'decision' =>
                                        $decision,

                                    'warranty_status' =>
                                        $warrantyStatus,

                                    'parts' =>
                                        $partSummary,

                                    'parts_cost_total' =>
                                        round(
                                            $partsCostTotal,
                                            2
                                        ),

                                    'parts_sale_total' =>
                                        round(
                                            $partsSaleTotal,
                                            2
                                        ),

                                    'labor_amount' =>
                                        $labor,

                                    'other_amount' =>
                                        $other,

                                    'quote_total' =>
                                        $quoteTotal,

                                    'requires_customer_approval' =>
                                        $requiresCustomerApproval,

                                    'notes' =>
                                        $notes,

                                    'completed_at' =>
                                        now()
                                            ->toDateTimeString(),

                                    'completed_by_user_id' =>
                                        auth()->id(),

                                    'workflow_stage_preserved' =>
                                        $repair->
                                            workflow_stage,

                                    'repair_status_preserved' =>
                                        $repair->
                                            status,

                                    'source' =>
                                        'manager_review_costing_action',
                                ];

                                /*

                                 * BEXIA_ATC_NO_CUSTOMER_APPROVAL_AUTHORIZATION_V5_83_4C5G9C

                                 *

                                 * Si el Encargado determina que una reparación

                                 * cobrable NO requiere Vo.Bo. del cliente,

                                 * su revisión económica constituye la autorización

                                 * del importe final.

                                 *

                                 * NO se crea customer_approval ficticio.

                                 */

                                if (

                                    $chargeable

                                    && ! $requiresCustomerApproval

                                ) {

                                    $managerReview[

                                        'customer_approval_status'

                                    ] = 'not_required';


                                    $managerReview[

                                        'authorized_total'

                                    ] = $quoteTotal;


                                    $managerReview[

                                        'authorization_satisfied_at'

                                    ] = now()->toDateTimeString();


                                    $managerReview[

                                        'authorization_source'

                                    ] =

                                        'manager_review_no_customer_approval_required';

                                }


                                $metadata[
                                    'manager_review'
                                ] =
                                    $managerReview;

                                \Illuminate\Support\Facades\DB::
                                    table(
                                        'repair_orders'
                                    )
                                    ->where(
                                        'id',
                                        $repairId
                                    )
                                    ->update([
                                        'warranty_status' =>
                                            $warrantyStatus,

                                        'parts_cost_estimate' =>
                                            round(
                                                $partsCostTotal,
                                                2
                                            ),

                                        'labor_cost_estimate' =>
                                            $labor,

                                        'other_cost_estimate' =>
                                            $other,

                                        'quote_total' =>
                                            $quoteTotal,

                                        'approved_total_snapshot' =>
                                            $chargeable
                                            && ! $requiresCustomerApproval
                                                ? $quoteTotal
                                                : null,

                                        'requires_internal_approval' =>
                                            false,

                                        'requires_customer_approval' =>
                                            $requiresCustomerApproval,

                                        'quote_status' =>
                                            $quoteStatus,

                                        'quote_notes' =>
                                            $notes,

                                        'metadata' =>
                                            json_encode(
                                                $metadata,
                                                JSON_UNESCAPED_UNICODE
                                                | JSON_UNESCAPED_SLASHES
                                            ),

                                        'updated_at' =>
                                            now(),
                                    ]);

                                $eventColumns =
                                    \Illuminate\Support\Facades\Schema::
                                        getColumnListing(
                                            'service_case_events'
                                        );

                                $event = [
                                    'company_id' =>
                                        $repair->
                                            company_id,

                                    'service_case_id' =>
                                        $repair->
                                            service_case_id,

                                    'repair_order_id' =>
                                        $repairId,

                                    'event_type' =>
                                        'manager_review_costed',

                                    'from_status' =>
                                        $repair->status,

                                    'to_status' =>
                                        $repair->status,

                                    'performed_by' =>
                                        auth()->id(),

                                    'performed_at' =>
                                        now(),

                                    'notes' =>
                                        $notes,

                                    'old_values' =>
                                        json_encode(
                                            $oldValues,
                                            JSON_UNESCAPED_UNICODE
                                            | JSON_UNESCAPED_SLASHES
                                        ),

                                    'new_values' =>
                                        json_encode(
                                            [
                                                'decision' =>
                                                    $decision,

                                                'warranty_status' =>
                                                    $warrantyStatus,

                                                'parts_cost_total' =>
                                                    round(
                                                        $partsCostTotal,
                                                        2
                                                    ),

                                                'parts_sale_total' =>
                                                    round(
                                                        $partsSaleTotal,
                                                        2
                                                    ),

                                                'labor_amount' =>
                                                    $labor,

                                                'other_amount' =>
                                                    $other,

                                                'quote_total' =>
                                                    $quoteTotal,

                                                'requires_customer_approval' =>
                                                    $requiresCustomerApproval,

                                                'quote_status' =>
                                                    $quoteStatus,
                                            ],
                                            JSON_UNESCAPED_UNICODE
                                            | JSON_UNESCAPED_SLASHES
                                        ),

                                    'metadata' =>
                                        json_encode(
                                            [
                                                'source' =>
                                                    'manager_review_costing_action',
                                            ],
                                            JSON_UNESCAPED_UNICODE
                                            | JSON_UNESCAPED_SLASHES
                                        ),

                                    'ip_address' =>
                                        request()?->ip(),

                                    'user_agent' =>
                                        request()
                                            ?->userAgent(),

                                    'created_at' =>
                                        now(),

                                    'updated_at' =>
                                        now(),
                                ];

                                $event =
                                    array_intersect_key(
                                        $event,
                                        array_flip(
                                            $eventColumns
                                        )
                                    );

                                \Illuminate\Support\Facades\DB::
                                    table(
                                        'service_case_events'
                                    )
                                    ->insert(
                                        $event
                                    );
                            }
                        );

                    $this->record
                        ->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title(
                            'Revisión y costeo registrados'
                        )
                        ->body(
                            'El trabajo técnico quedó '
                            . 'revisado. El siguiente paso '
                            . 'dependerá de si requiere '
                            . 'Vo.Bo. del cliente o si puede '
                            . 'pasar directamente a entrega.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        $this->getResource()::
                            getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $this->
                                            record,
                                ]
                            )
                    );
                }
            );
    }


    /*
     * BEXIA_ATC_POST_REPAIR_CUSTOMER_APPROVAL_ACTION_V5_83_4C5D2
     *
     * Vo.Bo. solicitado DESPUES del trabajo tecnico
     * y DESPUES del costeo del Encargado.
     *
     * No permite modificar importe ni componentes
     * economicos.
     */
    protected function postRepairCustomerApprovalAction(): Action
    {
        return Action::make(
            'post_repair_customer_approval'
        )
            ->label(
                'Registrar Vo.Bo. del cliente'
            )
            ->icon(
                'heroicon-o-check-badge'
            )
            ->color('success')
            ->modalHeading(
                'Registrar Vo.Bo. del cliente'
            )
            ->modalDescription(
                'La reparación ya fue realizada. '
                . 'Registra únicamente si el cliente '
                . 'autoriza o rechaza el importe '
                . 'calculado por el Encargado.'
            )
            ->modalWidth('5xl')
            ->modalSubmitActionLabel(
                'Registrar respuesta'
            )
            ->visible(
                fn (): bool =>
                    \App\Support\Service\ServiceAccess::
                        canRecordPostRepairCustomerDecision(
                            $this->record
                        )
            )
            ->form([
                \Filament\Forms\Components\Section::make(
                    'Importe presentado al cliente'
                )
                    ->columns(2)
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make(
                            'customer_approval_parts'
                        )
                            ->label(
                                'Refacciones / materiales'
                            )
                            ->content(
                                function (): \Illuminate\Support\HtmlString {
                                    $metadata =
                                        $this->record
                                            ?->metadata
                                        ?? [];

                                    if (
                                        is_string(
                                            $metadata
                                        )
                                    ) {
                                        $metadata =
                                            json_decode(
                                                $metadata,
                                                true
                                            )
                                            ?: [];
                                    }

                                    if (
                                        ! is_array(
                                            $metadata
                                        )
                                    ) {
                                        $metadata = [];
                                    }

                                    $parts =
                                        $metadata[
                                            'manager_review'
                                        ][
                                            'parts'
                                        ]
                                        ?? [];

                                    if (
                                        ! is_array(
                                            $parts
                                        )
                                        || $parts === []
                                    ) {
                                        return new
                                            \Illuminate\Support\HtmlString(
                                                'Sin refacciones'
                                            );
                                    }

                                    $lines = [];

                                    foreach (
                                        $parts
                                        as $part
                                    ) {
                                        $name =
                                            e(
                                                (string) (
                                                    $part[
                                                        'product_name'
                                                    ]
                                                    ?? 'Refacción'
                                                )
                                            );

                                        $qty =
                                            (float) (
                                                $part[
                                                    'quantity'
                                                ]
                                                ?? 0
                                            );

                                        $total =
                                            (float) (
                                                $part[
                                                    'total_price'
                                                ]
                                                ?? 0
                                            );

                                        $lines[] =
                                            '<div>'
                                            . $name
                                            . ' × '
                                            . number_format(
                                                $qty,
                                                2
                                            )
                                            . ' = <strong>$'
                                            . number_format(
                                                $total,
                                                2
                                            )
                                            . '</strong></div>';
                                    }

                                    return new
                                        \Illuminate\Support\HtmlString(
                                            implode(
                                                '',
                                                $lines
                                            )
                                        );
                                }
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'customer_approval_labor'
                        )
                            ->label(
                                'Mano de obra'
                            )
                            ->content(
                                function (): string {
                                    $metadata =
                                        $this->record
                                            ?->metadata
                                        ?? [];

                                    if (
                                        is_string(
                                            $metadata
                                        )
                                    ) {
                                        $metadata =
                                            json_decode(
                                                $metadata,
                                                true
                                            )
                                            ?: [];
                                    }

                                    $amount =
                                        (float) (
                                            $metadata[
                                                'manager_review'
                                            ][
                                                'labor_amount'
                                            ]
                                            ?? 0
                                        );

                                    return
                                        '$'
                                        . number_format(
                                            $amount,
                                            2
                                        );
                                }
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'customer_approval_other'
                        )
                            ->label(
                                'Otros cargos'
                            )
                            ->content(
                                function (): string {
                                    $metadata =
                                        $this->record
                                            ?->metadata
                                        ?? [];

                                    if (
                                        is_string(
                                            $metadata
                                        )
                                    ) {
                                        $metadata =
                                            json_decode(
                                                $metadata,
                                                true
                                            )
                                            ?: [];
                                    }

                                    $amount =
                                        (float) (
                                            $metadata[
                                                'manager_review'
                                            ][
                                                'other_amount'
                                            ]
                                            ?? 0
                                        );

                                    return
                                        '$'
                                        . number_format(
                                            $amount,
                                            2
                                        );
                                }
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'customer_approval_total'
                        )
                            ->label(
                                'TOTAL A AUTORIZAR'
                            )
                            ->content(
                                fn (): string =>
                                    '$'
                                    . number_format(
                                        (float) (
                                            $this->record
                                                ?->quote_total
                                            ?? 0
                                        ),
                                        2
                                    )
                            ),
                    ]),

                \Filament\Forms\Components\Radio::make(
                    'customer_decision'
                )
                    ->label(
                        'Respuesta del cliente'
                    )
                    ->options(
                        \App\Support\Service\ServiceRepairCustomerDecisionService::
                            DECISIONS
                    )
                    ->required()
                    ->live(),

                \Filament\Forms\Components\Select::make(
                    'customer_decision_channel'
                )
                    ->label(
                        'Medio de confirmación'
                    )
                    ->options(
                        \App\Support\Service\ServiceRepairCustomerDecisionService::
                            CHANNELS
                    )
                    ->default(
                        'whatsapp'
                    )
                    ->native(false)
                    ->required(),

                \Filament\Forms\Components\DateTimePicker::make(
                    'customer_decision_at'
                )
                    ->label(
                        'Fecha y hora de respuesta'
                    )
                    ->default(now())
                    ->seconds(false)
                    ->required(),

                \Filament\Forms\Components\Textarea::make(
                    'customer_decision_notes'
                )
                    ->label(
                        'Observaciones'
                    )
                    ->helperText(
                        'Ejemplo: Cliente confirma '
                        . 'por WhatsApp que autoriza '
                        . 'el importe total de $900.'
                    )
                    ->rows(4)
                    ->required()
                    ->maxLength(3000)
                    ->columnSpanFull(),

                \Filament\Forms\Components\FileUpload::make(
                    'customer_decision_files'
                )
                    ->label(
                        'Evidencia de la respuesta '
                        . '(opcional)'
                    )
                    ->helperText(
                        'Captura de WhatsApp, correo, '
                        . 'foto o documento.'
                    )
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                        'application/pdf',
                        'text/plain',
                    ])
                    ->disk('public')
                    ->directory(
                        'service/customer-quote-decisions'
                    )
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->imagePreviewHeight('120')
                    ->maxFiles(5)
                    ->maxSize(10240)
                    ->columnSpanFull(),
            ])
            ->action(
                function (
                    array $data
                ): void {
                    $record =
                        $this->record;

                    $decision =
                        (string) (
                            $data[
                                'customer_decision'
                            ]
                            ?? ''
                        );

                    $channel =
                        (string) (
                            $data[
                                'customer_decision_channel'
                            ]
                            ?? ''
                        );

                    $notes =
                        trim(
                            (string) (
                                $data[
                                    'customer_decision_notes'
                                ]
                                ?? ''
                            )
                        );

                    $files =
                        (array) (
                            $data[
                                'customer_decision_files'
                            ]
                            ?? []
                        );

                    try {
                        $record =
                            app(
                                \App\Support\Service\ServiceRepairCustomerDecisionService::class
                            )
                                ->recordPostRepairDecision(
                                    $record,
                                    $data
                                );
                    } catch (
                        \Throwable $exception
                    ) {
                        /*
                         * Evitar archivos huerfanos
                         * si la validacion/backend falla.
                         */
                        foreach (
                            $files
                            as $path
                        ) {
                            if (
                                is_string(
                                    $path
                                )
                                && $path !== ''
                            ) {
                                \Illuminate\Support\Facades\Storage::
                                    disk('public')
                                    ->delete(
                                        $path
                                    );
                            }
                        }

                        throw $exception;
                    }

                    if (
                        $files !== []
                    ) {
                        $approved =
                            $decision
                            === 'approved';

                        $channelLabel =
                            \App\Support\Service\ServiceRepairCustomerDecisionService::
                                CHANNELS[
                                    $channel
                                ]
                            ?? $channel;

                        $this->
                            saveServiceStageFilesForRepair(
                                record:
                                    $record,

                                paths:
                                    $files,

                                stage:
                                    $approved
                                        ? 'customer_post_repair_approval'
                                        : 'customer_post_repair_rejection',

                                notes:
                                    (
                                        $approved
                                            ? 'Evidencia Vo.Bo. post-reparación'
                                            : 'Evidencia rechazo post-reparación'
                                    )
                                    . ' - '
                                    . $channelLabel
                                    . ' - '
                                    . $notes,

                                eventType:
                                    'customer_post_repair_decision_evidence_uploaded',

                                eventDescription:
                                    'Se agregó evidencia de '
                                    . 'la respuesta del cliente '
                                    . 'al importe post-reparación.'
                            );
                    }

                    if (
                        $decision
                        === 'approved'
                    ) {
                        \Filament\Notifications\Notification::
                            make()
                            ->title(
                                'Vo.Bo. del cliente registrado'
                            )
                            ->body(
                                'El cliente autorizó $'
                                . number_format(
                                    (float) (
                                        $record->
                                            approved_total_snapshot
                                        ?? $record->
                                            quote_total
                                        ?? 0
                                    ),
                                    2
                                )
                                . '. El siguiente paso '
                                . 'es preparar cobro '
                                . 'y entrega.'
                            )
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::
                            make()
                            ->title(
                                'Cliente no autorizó el importe'
                            )
                            ->body(
                                'El costeo original se '
                                . 'conservó y la revisión '
                                . 'económica quedó reabierta '
                                . 'para el Encargado.'
                            )
                            ->warning()
                            ->send();
                    }

                    $this->redirect(
                        $this->getResource()::
                            getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $record,
                                ]
                            )
                    );
                }
            );
    }



    /*
     * BEXIA_ATC_POST_REPAIR_COLLECTION_DELIVERY_ACTION_V5_83_4C5E2
     *
     * Puente posterior a:
     * - trabajo tecnico terminado;
     * - revision/costeo terminado;
     * - Vo.Bo. cliente satisfecho.
     *
     * Objetivo:
     * - conservar exactamente el total autorizado;
     * - preparar economia para CxC;
     * - crear CxC nativa de Bexia;
     * - NO registrar pago aqui;
     * - marcar fisicamente listo para entrega;
     * - registrar politica de cobro;
     * - dejar a ServiceRepairReceivableSyncer
     *   como fuente de verdad del pago.
     *
     * No usa ServiceEconomicClosureCalculator porque
     * el flujo post-repair nuevo trata quote_total /
     * approved_total_snapshot como TOTAL FINAL
     * autorizado por el cliente.
     */

    /*
     * BEXIA_ATC_POST_REPAIR_NO_CHARGE_DELIVERY_V5_83_4C5F4
     *
     * Puente exclusivo para:
     * - garantia aceptada;
     * - cortesia.
     *
     * Reglas:
     * - total cliente = 0;
     * - NO crea CxC;
     * - NO registra pago;
     * - conserva costos internos ya capturados;
     * - deja el equipo listo para entrega;
     * - la entrega fisica sigue pasando por C5E8,
     *   con evidencia y firma obligatorias.
     */
    protected function preparePostRepairNoChargeDeliveryAction(): Action
    {
        return Action::make(
            'prepare_post_repair_no_charge_delivery'
        )
            ->label(
                'Preparar entrega sin cobro'
            )
            ->icon(
                'heroicon-o-gift'
            )
            ->color('success')
            ->modalHeading(
                'Preparar entrega sin cobro'
            )
            ->modalDescription(
                'Esta reparación quedó autorizada como garantía '
                . 'o cortesía. El cliente pagará $0.00. '
                . 'No se creará cuenta por cobrar ni pago.'
            )
            ->modalSubmitActionLabel(
                'Preparar entrega'
            )
            ->visible(
                fn (): bool =>
                    $this->
                        canPreparePostRepairNoChargeDelivery()
            )
            ->form([
                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5f4_repair_folio'
                    )
                    ->label('Orden técnica')
                    ->content(
                        fn (): string =>
                            (string) (
                                $this->record->
                                    folio
                                ?? (
                                    '#'
                                    . $this->record->
                                        getKey()
                                )
                            )
                    ),

                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5f4_decision'
                    )
                    ->label('Decisión')
                    ->content(
                        function (): string {
                            $metadata =
                                $this->
                                    postRepairMetadataArray(
                                        $this->record->
                                            metadata
                                        ?? null
                                    );

                            $decision =
                                (string) data_get(
                                    $metadata,
                                    'manager_review.decision',
                                    ''
                                );

                            return match (
                                $decision
                            ) {
                                'garantia' =>
                                    'Garantía aceptada / sin cargo',

                                'cortesia' =>
                                    'Cortesía / sin cargo',

                                default =>
                                    'No aplica',
                            };
                        }
                    ),

                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5f4_customer_total'
                    )
                    ->label('Total al cliente')
                    ->content(
                        '$0.00 · Sin cargo'
                    ),

                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5f4_cxc'
                    )
                    ->label('Cuenta por cobrar')
                    ->content(
                        'No se creará CxC ni se registrará pago.'
                    ),

                \Filament\Forms\Components\Textarea::
                    make(
                        'no_charge_notes'
                    )
                    ->label(
                        'Observaciones para la entrega'
                    )
                    ->helperText(
                        'Deja constancia de por qué el equipo '
                        . 'se entrega sin cobro.'
                    )
                    ->rows(4)
                    ->maxLength(3000)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(
                function (
                    array $data
                ): void {
                    if (
                        ! auth()->check()
                        || ! ServiceAccess::
                            hasServiceRole([
                                'Servicio - Encargado de Técnicos',
                                'Servicio - Supervisor',
                            ])
                        || ! ServiceAccess::can(
                            'service.repairs.update'
                        )
                    ) {
                        throw new
                            \Illuminate\Auth\Access\AuthorizationException(
                                'No tienes permiso para preparar '
                                . 'una entrega sin cobro.'
                            );
                    }

                    $repairId =
                        (int) (
                            $this->record
                                ?->getKey()
                            ?? 0
                        );

                    if ($repairId <= 0) {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'repair' =>
                                        'No se encontró la reparación.',
                                ]);
                    }

                    $notes =
                        trim(
                            (string) (
                                $data[
                                    'no_charge_notes'
                                ]
                                ?? ''
                            )
                        );

                    if ($notes === '') {
                        throw
                            \Illuminate\Validation\ValidationException::
                                withMessages([
                                    'no_charge_notes' =>
                                        'Captura las observaciones '
                                        . 'de la entrega sin cobro.',
                                ]);
                    }

                    $result =
                        \Illuminate\Support\Facades\DB::
                            transaction(
                                function () use (
                                    $repairId,
                                    $notes
                                ): array {
                                    $repair =
                                        \App\Models\RepairOrder::
                                            query()
                                            ->whereKey(
                                                $repairId
                                            )
                                            ->lockForUpdate()
                                            ->firstOrFail();

                                    $metadata =
                                        $this->
                                            postRepairMetadataArray(
                                                $repair->
                                                    metadata
                                                ?? null
                                            );

                                    $technicalWork =
                                        data_get(
                                            $metadata,
                                            'technical_work',
                                            []
                                        );

                                    $managerReview =
                                        data_get(
                                            $metadata,
                                            'manager_review',
                                            []
                                        );

                                    $decision =
                                        (string) (
                                            $managerReview[
                                                'decision'
                                            ]
                                            ?? ''
                                        );

                                    $quoteTotal =
                                        round(
                                            (float) (
                                                $repair->
                                                    quote_total
                                                ?? 0
                                            ),
                                            2
                                        );

                                    $managerTotal =
                                        round(
                                            (float) (
                                                $managerReview[
                                                    'quote_total'
                                                ]
                                                ?? 0
                                            ),
                                            2
                                        );

                                    $snapshot =
                                        $repair->
                                            approved_total_snapshot;

                                    $snapshotNonZero =
                                        $snapshot !== null
                                        && abs(
                                            (float) $snapshot
                                        ) > 0.009;

                                    $expectedWarranty =
                                        $decision === 'garantia'
                                            ? 'aceptada'
                                            : 'no_aplica';

                                    if (
                                        (string) (
                                            $repair->status
                                            ?? ''
                                        ) !== 'recibido'
                                        || (string) (
                                            $repair->
                                                workflow_stage
                                            ?? ''
                                        ) !== 'quote_draft'
                                        || (string) (
                                            $repair->
                                                quote_status
                                            ?? ''
                                        ) !== 'not_required'
                                        || (bool) (
                                            $repair->
                                                requires_customer_approval
                                            ?? false
                                        )
                                        || ! empty(
                                            $repair->
                                                account_receivable_id
                                        )
                                        || ! empty(
                                            $repair->
                                                ready_for_delivery_at
                                        )
                                        || ! empty(
                                            $repair->
                                                delivered_at
                                        )
                                        || ! empty(
                                            $repair->
                                                economic_closed_at
                                        )
                                        || trim(
                                            (string) (
                                                $repair->
                                                    economic_status
                                                ?? ''
                                            )
                                        ) !== ''
                                        || data_get(
                                            $metadata,
                                            'post_repair_collection_delivery'
                                        ) !== null
                                        || (
                                            $technicalWork[
                                                'status'
                                            ]
                                            ?? null
                                        ) !== 'completed'
                                        || (
                                            $managerReview[
                                                'status'
                                            ]
                                            ?? null
                                        ) !== 'completed'
                                        || ! in_array(
                                            $decision,
                                            [
                                                'garantia',
                                                'cortesia',
                                            ],
                                            true
                                        )
                                        || abs(
                                            $quoteTotal
                                        ) > 0.009
                                        || abs(
                                            $managerTotal
                                        ) > 0.009
                                        || $snapshotNonZero
                                        || (string) (
                                            $repair->
                                                warranty_status
                                            ?? ''
                                        ) !== $expectedWarranty
                                    ) {
                                        throw
                                            \Illuminate\Validation\ValidationException::
                                                withMessages([
                                                    'repair' =>
                                                        'La reparación ya no '
                                                        . 'cumple las reglas '
                                                        . 'de garantía/cortesía '
                                                        . 'sin cargo. '
                                                        . 'Actualiza la página '
                                                        . 'y vuelve a revisar.',
                                                ]);
                                    }

                                    if (
                                        ! \Illuminate\Support\Facades\Schema::
                                            hasTable(
                                                'account_receivables'
                                            )
                                    ) {
                                        throw new
                                            \RuntimeException(
                                                'No existe la tabla '
                                                . 'account_receivables.'
                                            );
                                    }

                                    $receivableExists =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'account_receivables'
                                            )
                                            ->where(
                                                'source_type',
                                                'service_repair_order'
                                            )
                                            ->where(
                                                'source_id',
                                                $repairId
                                            )
                                            ->exists();

                                    if ($receivableExists) {
                                        throw
                                            \Illuminate\Validation\ValidationException::
                                                withMessages([
                                                    'repair' =>
                                                        'Ya existe una cuenta '
                                                        . 'por cobrar vinculada '
                                                        . 'a esta reparación. '
                                                        . 'No puede prepararse '
                                                        . 'como sin cobro.',
                                                ]);
                                    }

                                    $now = now();

                                    $metadata[
                                        'post_repair_collection_delivery'
                                    ] = [
                                        'status' =>
                                            'prepared',

                                        'collection_policy' =>
                                            'no_charge',

                                        'authorized_total' =>
                                            0.0,

                                        'final_amount' =>
                                            0.0,

                                        'account_receivable_id' =>
                                            null,

                                        'account_receivable_number' =>
                                            null,

                                        'payment_recorded_here' =>
                                            false,

                                        'tax_recalculated' =>
                                            false,

                                        'tax_handling' =>
                                            'no_charge',

                                        'no_charge_decision' =>
                                            $decision,

                                        'customer_approval_required' =>
                                            false,

                                        'prepared_at' =>
                                            $now->
                                                toDateTimeString(),

                                        'prepared_by_user_id' =>
                                            auth()->id(),

                                        'notes' =>
                                            $notes,

                                        'source' =>
                                            'post_repair_no_charge_delivery_bridge',
                                    ];

                                    $oldStatus =
                                        (string) (
                                            $repair->status
                                            ?? ''
                                        );

                                    $repair->
                                        forceFill([
                                            'status' =>
                                                'ready_for_delivery',

                                            'workflow_stage' =>
                                                'ready_for_delivery',

                                            'ready_for_delivery_at' =>
                                                $now,

                                            'approved_total_snapshot' =>
                                                0,

                                            'economic_subtotal' =>
                                                0,

                                            'economic_tax' =>
                                                0,

                                            'economic_total' =>
                                                0,

                                            'total_amount' =>
                                                0,

                                            'metadata' =>
                                                $metadata,
                                        ])
                                        ->save();

                                    if (
                                        ! empty(
                                            $repair->
                                                service_case_id
                                        )
                                        && \Illuminate\Support\Facades\Schema::
                                            hasTable(
                                                'service_cases'
                                            )
                                    ) {
                                        $caseQuery =
                                            \Illuminate\Support\Facades\DB::
                                                table(
                                                    'service_cases'
                                                )
                                                ->where(
                                                    'id',
                                                    (int) $repair->
                                                        service_case_id
                                                );

                                        if (
                                            \Illuminate\Support\Facades\Schema::
                                                hasColumn(
                                                    'service_cases',
                                                    'company_id'
                                                )
                                            && ! empty(
                                                $repair->
                                                    company_id
                                            )
                                        ) {
                                            $caseQuery->
                                                where(
                                                    'company_id',
                                                    (int) $repair->
                                                        company_id
                                                );
                                        }

                                        $caseQuery->
                                            update([
                                                'status' =>
                                                    'listo_entrega',

                                                'updated_at' =>
                                                    $now,
                                            ]);
                                    }

                                    $repair->refresh();

                                    \App\Filament\Resources\RepairOrderResource::
                                        logEvent(
                                            $repair,
                                            'post_repair_no_charge_delivery_prepared',
                                            $oldStatus,
                                            'ready_for_delivery',
                                            $notes
                                        );

                                    return [
                                        'decision' =>
                                            $decision,

                                        'folio' =>
                                            (string) (
                                                $repair->folio
                                                ?? (
                                                    '#'
                                                    . $repair->
                                                        getKey()
                                                )
                                            ),
                                    ];
                                }
                            );

                    $decisionLabel =
                        (
                            $result[
                                'decision'
                            ]
                            ?? ''
                        ) === 'garantia'
                            ? 'Garantía aceptada'
                            : 'Cortesía';

                    $this->record->refresh();

                    \Filament\Notifications\Notification::
                        make()
                        ->title(
                            'Entrega sin cobro preparada'
                        )
                        ->body(
                            $decisionLabel
                            . '. Cliente: $0.00. '
                            . 'No se creó CxC ni pago. '
                            . 'El equipo ya puede pasar '
                            . 'a la entrega física.'
                        )
                        ->success()
                        ->send();

                    /*
                     * C5G12_NO_CHARGE_REDIRECT_TO_TICKET
                     *
                     * Una vez preparado el puente económico,
                     * Reparaciones termina su etapa operativa.
                     *
                     * Salida, impresión y entrega continúan
                     * desde el ticket ATC.
                     */
                    $caseId =
                        (int) (
                            $this->record->
                                service_case_id
                            ?? 0
                        );

                    if ($caseId > 0) {
                        $this->redirect(
                            \App\Filament\Resources\ServiceCaseResource::
                                getUrl(
                                    'edit',
                                    [
                                        'record' =>
                                            $caseId,
                                    ]
                                )
                        );

                        return;
                    }


                    $this->redirect(
                        $this->
                            getResource()::
                            getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $this->record,
                                ]
                            )
                    );
                }
            );
    }


    protected function preparePostRepairCollectionDeliveryAction(): Action
    {
        return Action::make(
            'prepare_post_repair_collection_delivery'
        )
            ->label(
                'Preparar cobro y entrega'
            )
            ->icon(
                'heroicon-o-banknotes'
            )
            ->color('success')
            ->modalHeading(
                'Preparar cobro y entrega'
            )
            ->modalDescription(
                'Se creará una cuenta por cobrar por el total autorizado y el equipo quedará listo para entrega. Esta acción NO registra un pago.'
            )
            ->modalSubmitActionLabel(
                'Preparar cobro y entrega'
            )
            ->visible(
                fn (): bool =>
                    $this->
                        canPreparePostRepairCollectionDelivery()
            )
            ->form([
                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5e2_repair_folio'
                    )
                    ->label('Orden técnica')
                    ->content(
                        fn (): string =>
                            (string) (
                                $this->record->
                                    folio
                                ?? '—'
                            )
                    ),

                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5e2_customer'
                    )
                    ->label('Cliente')
                    ->content(
                        function (): string {
                            $caseId =
                                (int) (
                                    $this->record->
                                        service_case_id
                                    ?? 0
                                );

                            if ($caseId <= 0) {
                                return '—';
                            }

                            return
                                (string) (
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'service_cases'
                                        )
                                        ->where(
                                            'id',
                                            $caseId
                                        )
                                        ->value(
                                            'contact_name'
                                        )
                                    ?: '—'
                                );
                        }
                    ),

                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5e2_authorized_total'
                    )
                    ->label(
                        'Total autorizado'
                    )
                    ->content(
                        fn (): string =>
                            '$'
                            . number_format(
                                $this->
                                    postRepairAuthorizedTotal(),
                                2
                            )
                            . ' MXN'
                    ),

                \Filament\Forms\Components\Placeholder::
                    make(
                        'c5e2_notice'
                    )
                    ->label(
                        'Qué hará esta acción'
                    )
                    ->content(
                        '1) Creará la CxC nativa de Bexia. '
                        . '2) No se registrará ningún pago. '
                        . '3) Cobrar antes de salir: la salida y la entrega esperan el pago. '
                        . '4) Cobrar al entregar: permite generar la SAL-ATC para salir a entregar, pero la entrega final exige registrar primero el pago. '
                        . '5) Crédito autorizado: permite entregar con saldo pendiente.'
                    )
                    ->columnSpanFull(),

                \Filament\Forms\Components\Radio::
                    make(
                        'collection_policy'
                    )
                    ->label(
                        'Condición para entregar'
                    )
                    /*
                     * BEXIA_ATC_SPLIT_PAYMENT_TIMING_V5_83_4C5G10
                     *
                     * Antes de entrega y al momento de entrega
                     * son operaciones distintas.
                     */
                    ->options([
                        'payment_before_delivery' =>
                            'Cobrar antes de salir a entrega',

                        'payment_on_delivery' =>
                            'Cobrar al momento de entregar',

                        'credit_allowed' =>
                            'Crédito autorizado / permitir entrega con saldo pendiente',
                    ])
                    ->default(
                        'payment_before_delivery'
                    )
                    ->required()
                    ->columns(1)
                    ->helperText(
                        'El pago siempre se registra en Cuentas por cobrar. La opción seleccionada define si la SAL-ATC puede generarse antes del pago y si la entrega final puede realizarse con saldo pendiente.'
                    )
                    ->columnSpanFull(),

                \Filament\Forms\Components\Textarea::
                    make(
                        'preparation_notes'
                    )
                    ->label(
                        'Observaciones'
                    )
                    ->rows(3)
                    ->required()
                    ->maxLength(2000)
                    ->helperText(
                        'Indica la condición acordada de cobro o cualquier instrucción necesaria antes de entregar.'
                    )
                    ->columnSpanFull(),
            ])
            ->action(
                function (array $data): void {
                    $repairId =
                        (int) (
                            $this->record->
                                getKey()
                        );

                    $policy =
                        trim(
                            (string) (
                                $data[
                                    'collection_policy'
                                ]
                                ?? ''
                            )
                        );

                    $notes =
                        trim(
                            (string) (
                                $data[
                                    'preparation_notes'
                                ]
                                ?? ''
                            )
                        );

                    if (
                        ! in_array(
                            $policy,
                            [
                                'payment_before_delivery',
                                'payment_on_delivery',
                                'credit_allowed',
                            ],
                            true
                        )
                    ) {
                        throw new \RuntimeException(
                            'La condición de cobro no es válida.'
                        );
                    }

                    if ($notes === '') {
                        throw new \RuntimeException(
                            'Las observaciones son obligatorias.'
                        );
                    }

                    $result =
                        \Illuminate\Support\Facades\DB::
                            transaction(
                                function () use (
                                    $repairId,
                                    $policy,
                                    $notes
                                ): array {
                                    $repair =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'repair_orders'
                                            )
                                            ->where(
                                                'id',
                                                $repairId
                                            )
                                            ->lockForUpdate()
                                            ->first();

                                    if (! $repair) {
                                        throw new \RuntimeException(
                                            'No se encontró la reparación.'
                                        );
                                    }

                                    if (
                                        (string) (
                                            $repair->status
                                            ?? ''
                                        ) !== 'recibido'
                                        || (string) (
                                            $repair->
                                                workflow_stage
                                            ?? ''
                                        ) !== 'quote_draft'
                                    ) {
                                        throw new \RuntimeException(
                                            'La reparación ya no está en el estado esperado.'
                                        );
                                    }

                                    /*
                                     * BEXIA_ATC_COLLECTION_RUNTIME_NO_VOBO_V5_83_4C5G9C
                                     *
                                     * Un Vo.Bo. pendiente sigue bloqueando.
                                     * Si el Encargado indicó que NO se
                                     * requiere Vo.Bo., la validación completa
                                     * se realiza después con el helper común.
                                     */
                                    if (
                                        (bool) (
                                            $repair->
                                                requires_customer_approval
                                            ?? false
                                        )
                                    ) {
                                        throw new \RuntimeException(
                                            'La autorización post-reparación todavía está pendiente.'
                                        );
                                    }

                                    if (
                                        ! empty(
                                            $repair->
                                                account_receivable_id
                                        )
                                        || ! empty(
                                            $repair->
                                                ready_for_delivery_at
                                        )
                                        || ! empty(
                                            $repair->
                                                delivered_at
                                        )
                                        || ! empty(
                                            $repair->
                                                economic_closed_at
                                        )
                                    ) {
                                        throw new \RuntimeException(
                                            'Cobro o entrega ya fueron preparados previamente.'
                                        );
                                    }

                                    $existingCxc =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'account_receivables'
                                            )
                                            ->where(
                                                'source_type',
                                                'service_repair_order'
                                            )
                                            ->where(
                                                'source_id',
                                                $repairId
                                            )
                                            ->exists();

                                    if ($existingCxc) {
                                        throw new \RuntimeException(
                                            'Ya existe una CxC para esta reparación.'
                                        );
                                    }

                                    $metadata =
                                        $this->
                                            postRepairMetadataArray(
                                                $repair->
                                                    metadata
                                                ?? null
                                            );

                                    $authorization =
                                        $this->
                                            postRepairCollectionAuthorizationForRecord(
                                                $repair,
                                                $metadata
                                            );

                                    if (
                                        ! (
                                            $authorization[
                                                'ok'
                                            ]
                                            ?? false
                                        )
                                    ) {
                                        throw new \RuntimeException(
                                            (string) (
                                                $authorization[
                                                    'reason'
                                                ]
                                                ?? 'La autorización post-reparación no está completa.'
                                            )
                                        );
                                    }

                                    if (
                                        data_get(
                                            $metadata,
                                            'post_repair_collection_delivery'
                                        ) !== null
                                    ) {
                                        throw new \RuntimeException(
                                            'El puente de cobro y entrega ya fue registrado.'
                                        );
                                    }

                                    $quoteTotal =
                                        round(
                                            (float) (
                                                $repair->
                                                    quote_total
                                                ?? 0
                                            ),
                                            2
                                        );

                                    $approvedSnapshot =
                                        round(
                                            (float) (
                                                $repair->
                                                    approved_total_snapshot
                                                ?? 0
                                            ),
                                            2
                                        );

                                    $customerAuthorized =
                                        (
                                            (
                                                $authorization[
                                                    'branch'
                                                ]
                                                ?? ''
                                            )
                                            ===
                                            'manager_no_customer_approval'
                                        )
                                            ? $quoteTotal
                                            : round(
                                                (float) data_get(
                                                    $metadata,
                                                    'customer_approval.authorized_amount',
                                                    0
                                                ),
                                                2
                                            );

                                    $managerAuthorized =
                                        round(
                                            (float) data_get(
                                                $metadata,
                                                'manager_review.authorized_total',
                                                0
                                            ),
                                            2
                                        );

                                    if (
                                        $quoteTotal <= 0
                                        || abs(
                                            $quoteTotal
                                            - $approvedSnapshot
                                        ) > 0.009
                                        || abs(
                                            $quoteTotal
                                            - $customerAuthorized
                                        ) > 0.009
                                        || abs(
                                            $quoteTotal
                                            - $managerAuthorized
                                        ) > 0.009
                                    ) {
                                        throw new \RuntimeException(
                                            'Los importes de costeo y autorización no coinciden.'
                                        );
                                    }

                                    $partsCost =
                                        round(
                                            (float) data_get(
                                                $metadata,
                                                'manager_review.parts_cost_total',
                                                0
                                            ),
                                            2
                                        );

                                    $partsSale =
                                        round(
                                            (float) data_get(
                                                $metadata,
                                                'manager_review.parts_sale_total',
                                                0
                                            ),
                                            2
                                        );

                                    $laborSale =
                                        round(
                                            (float) data_get(
                                                $metadata,
                                                'manager_review.labor_amount',
                                                0
                                            ),
                                            2
                                        );

                                    $otherAmount =
                                        round(
                                            (float) data_get(
                                                $metadata,
                                                'manager_review.other_amount',
                                                0
                                            ),
                                            2
                                        );

                                    $managerComponents =
                                        round(
                                            $partsSale
                                            + $laborSale
                                            + $otherAmount,
                                            2
                                        );

                                    if (
                                        abs(
                                            $managerComponents
                                            - $quoteTotal
                                        ) > 0.009
                                    ) {
                                        throw new \RuntimeException(
                                            'La suma de refacciones, mano de obra y otros cargos no coincide con el total autorizado.'
                                        );
                                    }

                                    $evidenceCount =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'service_attachments'
                                            )
                                            ->where(
                                                'repair_order_id',
                                                $repairId
                                            )
                                            ->where(
                                                'stage',
                                                'repair_result'
                                            )
                                            ->count();

                                    if ($evidenceCount < 1) {
                                        throw new \RuntimeException(
                                            'Falta evidencia técnica repair_result.'
                                        );
                                    }

                                    $now = now();

                                    /*
                                     * IMPORTANTE:
                                     * $quoteTotal ya es TOTAL FINAL
                                     * autorizado por cliente.
                                     *
                                     * No se vuelve a sumar 16%.
                                     * El desglose fiscal posterior
                                     * corresponde al flujo fiscal.
                                     */
                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_orders'
                                        )
                                        ->where(
                                            'id',
                                            $repairId
                                        )
                                        ->update([
                                            'parts_cost_total' =>
                                                $partsCost,

                                            'parts_sale_total' =>
                                                $partsSale,

                                            'labor_sale_total' =>
                                                $laborSale,

                                            'economic_subtotal' =>
                                                $quoteTotal,

                                            'economic_tax' =>
                                                0,

                                            'economic_total' =>
                                                $quoteTotal,

                                            'total_amount' =>
                                                $quoteTotal,

                                            'economic_status' =>
                                                'ready_to_charge',

                                            'economic_requires_approval' =>
                                                false,

                                            'ready_to_charge_at' =>
                                                $now,

                                            'economic_notes' =>
                                                $notes,

                                            'updated_at' =>
                                                $now,
                                        ]);

                                    $receivableResult =
                                        ServiceReceivableCreator::
                                            createForRepairOrder(
                                                $repairId,
                                                [
                                                    'created_by' =>
                                                        auth()->id(),
                                                ]
                                            );

                                    $receivableId =
                                        (int) (
                                            $receivableResult[
                                                'account_receivable_id'
                                            ]
                                            ?? $receivableResult[
                                                'id'
                                            ]
                                            ?? 0
                                        );

                                    if ($receivableId <= 0) {
                                        $linkedId =
                                            \Illuminate\Support\Facades\DB::
                                                table(
                                                    'repair_orders'
                                                )
                                                ->where(
                                                    'id',
                                                    $repairId
                                                )
                                                ->value(
                                                    'account_receivable_id'
                                                );

                                        $receivableId =
                                            (int) (
                                                $linkedId
                                                ?? 0
                                            );
                                    }

                                    if ($receivableId <= 0) {
                                        throw new \RuntimeException(
                                            'La CxC no pudo enlazarse a la reparación.'
                                        );
                                    }

                                    $receivable =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'account_receivables'
                                            )
                                            ->where(
                                                'id',
                                                $receivableId
                                            )
                                            ->lockForUpdate()
                                            ->first();

                                    if (! $receivable) {
                                        throw new \RuntimeException(
                                            'La CxC creada no existe.'
                                        );
                                    }

                                    if (
                                        (string) (
                                            $receivable->
                                                source_type
                                            ?? ''
                                        )
                                        !==
                                        'service_repair_order'
                                        || (int) (
                                            $receivable->
                                                source_id
                                            ?? 0
                                        ) !== $repairId
                                    ) {
                                        throw new \RuntimeException(
                                            'La CxC no corresponde a esta reparación.'
                                        );
                                    }

                                    if (
                                        abs(
                                            round(
                                                (float) (
                                                    $receivable->
                                                        total
                                                    ?? 0
                                                ),
                                                2
                                            )
                                            - $quoteTotal
                                        ) > 0.009
                                    ) {
                                        throw new \RuntimeException(
                                            'El total de la CxC no coincide con el autorizado.'
                                        );
                                    }

                                    if (
                                        abs(
                                            round(
                                                (float) (
                                                    $receivable->
                                                        collected_total
                                                    ?? 0
                                                ),
                                                2
                                            )
                                        ) > 0.009
                                    ) {
                                        throw new \RuntimeException(
                                            'La CxC recién creada no debe tener cobros.'
                                        );
                                    }

                                    if (
                                        abs(
                                            round(
                                                (float) (
                                                    $receivable->
                                                        balance_total
                                                    ?? 0
                                                ),
                                                2
                                            )
                                            - $quoteTotal
                                        ) > 0.009
                                    ) {
                                        throw new \RuntimeException(
                                            'El saldo de la CxC no coincide con el autorizado.'
                                        );
                                    }

                                    $paymentCount =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'account_receivable_payments'
                                            )
                                            ->where(
                                                'account_receivable_id',
                                                $receivableId
                                            )
                                            ->count();

                                    if ($paymentCount !== 0) {
                                        throw new \RuntimeException(
                                            'No debe existir ningún pago al preparar la CxC.'
                                        );
                                    }

                                    $postCreatorRepair =
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'repair_orders'
                                            )
                                            ->where(
                                                'id',
                                                $repairId
                                            )
                                            ->lockForUpdate()
                                            ->first();

                                    if (! $postCreatorRepair) {
                                        throw new \RuntimeException(
                                            'No se pudo recargar la reparación.'
                                        );
                                    }

                                    $freshMetadata =
                                        $this->
                                            postRepairMetadataArray(
                                                $postCreatorRepair->
                                                    metadata
                                                ?? null
                                            );

                                    $freshMetadata[
                                        'post_repair_collection_delivery'
                                    ] = [
                                        'status' =>
                                            'prepared',

                                        'collection_policy' =>
                                            $policy,

                                        'authorized_total' =>
                                            $quoteTotal,

                                        'final_amount' =>
                                            $quoteTotal,

                                        'account_receivable_id' =>
                                            $receivableId,

                                        'account_receivable_number' =>
                                            (string) (
                                                $receivable->
                                                    number
                                                ?? ''
                                            ),

                                        'payment_recorded_here' =>
                                            false,

                                        'tax_recalculated' =>
                                            false,

                                        'tax_handling' =>
                                            'approved_total_preserved_as_final_amount',

                                        'prepared_at' =>
                                            $now->
                                                toDateTimeString(),

                                        'prepared_by_user_id' =>
                                            auth()->id(),

                                        'notes' =>
                                            $notes,

                                        'source' =>
                                            'post_repair_collection_delivery_bridge',
                                    ];

                                    \Illuminate\Support\Facades\DB::
                                        table(
                                            'repair_orders'
                                        )
                                        ->where(
                                            'id',
                                            $repairId
                                        )
                                        ->update([
                                            'status' =>
                                                'ready_for_delivery',

                                            'workflow_stage' =>
                                                'ready_for_delivery',

                                            'ready_for_delivery_at' =>
                                                $now,

                                            'metadata' =>
                                                json_encode(
                                                    $freshMetadata,
                                                    JSON_UNESCAPED_UNICODE
                                                    | JSON_UNESCAPED_SLASHES
                                                ),

                                            'updated_at' =>
                                                $now,
                                        ]);

                                    if (
                                        ! empty(
                                            $postCreatorRepair->
                                                service_case_id
                                        )
                                    ) {
                                        \Illuminate\Support\Facades\DB::
                                            table(
                                                'service_cases'
                                            )
                                            ->where(
                                                'id',
                                                (int) (
                                                    $postCreatorRepair->
                                                        service_case_id
                                                )
                                            )
                                            ->update([
                                                'status' =>
                                                    'listo_entrega',

                                                'updated_at' =>
                                                    $now,
                                            ]);
                                    }

                                    $eventRecord =
                                        \App\Models\RepairOrder::
                                            query()
                                            ->find(
                                                $repairId
                                            );

                                    if (! $eventRecord) {
                                        throw new \RuntimeException(
                                            'No se pudo crear la auditoría de transición.'
                                        );
                                    }

                                    RepairOrderResource::
                                        logEvent(
                                            $eventRecord,
                                            'post_repair_collection_delivery_prepared',
                                            'recibido',
                                            'ready_for_delivery',
                                            'Cobro y entrega preparados. '
                                            . 'CxC '
                                            . (
                                                $receivable->
                                                    number
                                                ?? (
                                                    '#'
                                                    . $receivableId
                                                )
                                            )
                                            . '. Política: '
                                            . $policy
                                            . '. No se registró pago.'
                                        );

                                    return [
                                        'receivable_id' =>
                                            $receivableId,

                                        'receivable_number' =>
                                            (string) (
                                                $receivable->
                                                    number
                                                ?? (
                                                    '#'
                                                    . $receivableId
                                                )
                                            ),

                                        'total' =>
                                            $quoteTotal,

                                        'policy' =>
                                            $policy,
                                    ];
                                }
                            );

                    $this->record->refresh();

                    $body =
                        ($result[
                            'receivable_number'
                        ] ?? 'CxC')
                        . ' por $'
                        . number_format(
                            (float) (
                                $result[
                                    'total'
                                ]
                                ?? 0
                            ),
                            2
                        )
                        . '. ';

                    if (
                        (
                            $result[
                                'policy'
                            ]
                            ?? ''
                        )
                        ===
                        'payment_required'
                    ) {
                        $body .=
                            'El equipo está listo, pero la entrega permanecerá bloqueada hasta que la CxC esté pagada.';
                    } else {
                        $body .=
                            'El equipo está listo y la entrega puede continuar con saldo pendiente por crédito autorizado.';
                    }

                    \Filament\Notifications\Notification::
                        make()
                        ->title(
                            'Cobro y entrega preparados'
                        )
                        ->body($body)
                        ->success()
                        ->send();

                    /*
                     * C5G12_COLLECTION_REDIRECT_TO_TICKET
                     *
                     * Una vez preparado el puente económico,
                     * Reparaciones termina su etapa operativa.
                     *
                     * Salida, impresión y entrega continúan
                     * desde el ticket ATC.
                     */
                    $caseId =
                        (int) (
                            $this->record->
                                service_case_id
                            ?? 0
                        );

                    if ($caseId > 0) {
                        $this->redirect(
                            \App\Filament\Resources\ServiceCaseResource::
                                getUrl(
                                    'edit',
                                    [
                                        'record' =>
                                            $caseId,
                                    ]
                                )
                        );

                        return;
                    }


                    $this->redirect(
                        $this->
                            getResource()::
                            getUrl(
                                'edit',
                                [
                                    'record' =>
                                        $this->record,
                                ]
                            )
                    );
                }
            );
    }

    protected function postRepairMetadataArray(
        mixed $raw
    ): array {
        if (is_array($raw)) {
            return $raw;
        }

        if (
            $raw instanceof
            \Illuminate\Contracts\Support\Arrayable
        ) {
            return $raw->toArray();
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

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function postRepairAuthorizedTotal(): float
    {
        return round(
            (float) (
                $this->record->
                    approved_total_snapshot
                ?? $this->record->
                    quote_total
                ?? 0
            ),
            2
        );
    }


    protected function canPreparePostRepairNoChargeDelivery(): bool
    {
        $record =
            $this->record
            ?? null;

        if (
            ! $record
            || ! auth()->check()
        ) {
            return false;
        }

        if (
            ! ServiceAccess::hasServiceRole([
                'Servicio - Encargado de Técnicos',
                'Servicio - Supervisor',
            ])
            || ! ServiceAccess::can(
                'service.repairs.update'
            )
        ) {
            return false;
        }

        if (
            (string) (
                $record->status
                ?? ''
            ) !== 'recibido'
            || (string) (
                $record->workflow_stage
                ?? ''
            ) !== 'quote_draft'
            || (string) (
                $record->quote_status
                ?? ''
            ) !== 'not_required'
            || (bool) (
                $record->
                    requires_customer_approval
                ?? false
            )
        ) {
            return false;
        }

        if (
            ! empty(
                $record->
                    account_receivable_id
            )
            || ! empty(
                $record->
                    ready_for_delivery_at
            )
            || ! empty(
                $record->
                    delivered_at
            )
            || ! empty(
                $record->
                    economic_closed_at
            )
            || trim(
                (string) (
                    $record->
                        economic_status
                    ?? ''
                )
            ) !== ''
        ) {
            return false;
        }

        $metadata =
            $this->
                postRepairMetadataArray(
                    $record->metadata
                    ?? null
                );

        if (
            data_get(
                $metadata,
                'post_repair_collection_delivery'
            ) !== null
        ) {
            return false;
        }

        if (
            data_get(
                $metadata,
                'technical_work.status'
            ) !== 'completed'
            || data_get(
                $metadata,
                'manager_review.status'
            ) !== 'completed'
        ) {
            return false;
        }

        $decision =
            (string) data_get(
                $metadata,
                'manager_review.decision',
                ''
            );

        if (
            ! in_array(
                $decision,
                [
                    'garantia',
                    'cortesia',
                ],
                true
            )
        ) {
            return false;
        }

        $quoteTotal =
            round(
                (float) (
                    $record->
                        quote_total
                    ?? 0
                ),
                2
            );

        $managerTotal =
            round(
                (float) data_get(
                    $metadata,
                    'manager_review.quote_total',
                    0
                ),
                2
            );

        if (
            abs(
                $quoteTotal
            ) > 0.009
            || abs(
                $managerTotal
            ) > 0.009
        ) {
            return false;
        }

        if (
            $record->
                approved_total_snapshot
            !== null
            && abs(
                (float) $record->
                    approved_total_snapshot
            ) > 0.009
        ) {
            return false;
        }

        $expectedWarranty =
            $decision === 'garantia'
                ? 'aceptada'
                : 'no_aplica';

        if (
            (string) (
                $record->
                    warranty_status
                ?? ''
            ) !== $expectedWarranty
        ) {
            return false;
        }

        if (
            ! \Illuminate\Support\Facades\Schema::
                hasTable(
                    'account_receivables'
                )
        ) {
            return false;
        }

        return
            ! \Illuminate\Support\Facades\DB::
                table(
                    'account_receivables'
                )
                ->where(
                    'source_type',
                    'service_repair_order'
                )
                ->where(
                    'source_id',
                    (int) $record->
                        getKey()
                )
                ->exists();
    }



    /*
     * BEXIA_ATC_POST_REPAIR_AUTHORIZATION_HELPER_V5_83_4C5G9C
     *
     * Existen dos caminos válidos para servicio cobrable:
     *
     * 1. Vo.Bo. requerido:
     *    customer_approval.status=approved
     *    y todos los importes autorizados coinciden.
     *
     * 2. Vo.Bo. NO requerido:
     *    quote_status=not_required
     *    y la revisión económica del Encargado
     *    constituye la autorización final.
     *
     * Nunca se fabrica una aprobación del cliente.
     */
    protected function postRepairCollectionAuthorizationForRecord(
        object $record,
        ?array $metadata = null
    ): array {
        $metadata =
            $metadata
            ?? $this->
                postRepairMetadataArray(
                    $record->metadata
                    ?? null
                );

        if (
            data_get(
                $metadata,
                'technical_work.status'
            ) !== 'completed'
        ) {
            return [
                'ok' => false,
                'reason' =>
                    'El trabajo técnico todavía no está terminado.',
            ];
        }

        if (
            data_get(
                $metadata,
                'manager_review.status'
            ) !== 'completed'
        ) {
            return [
                'ok' => false,
                'reason' =>
                    'La revisión y costeo todavía no están terminados.',
            ];
        }

        $decision =
            (string) data_get(
                $metadata,
                'manager_review.decision',
                ''
            );

        if (
            ! in_array(
                $decision,
                [
                    'cobrable',
                    'garantia_rechazada',
                ],
                true
            )
        ) {
            return [
                'ok' => false,
                'reason' =>
                    'La reparación no corresponde al flujo cobrable.',
            ];
        }

        $quoteTotal =
            round(
                (float) (
                    $record->quote_total
                    ?? 0
                ),
                2
            );

        $managerTotal =
            round(
                (float) data_get(
                    $metadata,
                    'manager_review.quote_total',
                    0
                ),
                2
            );

        if (
            $quoteTotal <= 0
            || abs(
                $quoteTotal
                - $managerTotal
            ) > 0.009
        ) {
            return [
                'ok' => false,
                'reason' =>
                    'El importe final no coincide con la revisión del Encargado.',
            ];
        }

        if (
            (bool) (
                $record->
                    requires_customer_approval
                ?? false
            )
        ) {
            return [
                'ok' => false,
                'reason' =>
                    'Todavía está pendiente el Vo.Bo. del cliente.',
            ];
        }

        $quoteStatus =
            (string) (
                $record->quote_status
                ?? ''
            );

        $snapshot =
            round(
                (float) (
                    $record->
                        approved_total_snapshot
                    ?? 0
                ),
                2
            );

        $managerAuthorized =
            round(
                (float) data_get(
                    $metadata,
                    'manager_review.authorized_total',
                    0
                ),
                2
            );

        if (
            $quoteStatus
            === 'customer_approved'
        ) {
            $customerStatus =
                (string) data_get(
                    $metadata,
                    'customer_approval.status',
                    ''
                );

            $customerAuthorized =
                round(
                    (float) data_get(
                        $metadata,
                        'customer_approval.authorized_amount',
                        0
                    ),
                    2
                );

            if (
                $customerStatus !== 'approved'
                || abs(
                    $quoteTotal
                    - $snapshot
                ) > 0.009
                || abs(
                    $quoteTotal
                    - $customerAuthorized
                ) > 0.009
                || abs(
                    $quoteTotal
                    - $managerAuthorized
                ) > 0.009
            ) {
                return [
                    'ok' => false,
                    'reason' =>
                        'La autorización del cliente no es consistente con el importe final.',
                ];
            }

            return [
                'ok' => true,
                'branch' =>
                    'customer_approved',
                'authorized_total' =>
                    $quoteTotal,
            ];
        }

        if (
            $quoteStatus
            === 'not_required'
        ) {
            $managerRequiresCustomer =
                (bool) data_get(
                    $metadata,
                    'manager_review.requires_customer_approval',
                    false
                );

            $managerApprovalStatus =
                (string) data_get(
                    $metadata,
                    'manager_review.customer_approval_status',
                    ''
                );

            $authorizationSource =
                (string) data_get(
                    $metadata,
                    'manager_review.authorization_source',
                    ''
                );

            if (
                $managerRequiresCustomer
                || $managerApprovalStatus
                    !== 'not_required'
                || $authorizationSource
                    !==
                    'manager_review_no_customer_approval_required'
                || abs(
                    $quoteTotal
                    - $snapshot
                ) > 0.009
                || abs(
                    $quoteTotal
                    - $managerAuthorized
                ) > 0.009
            ) {
                return [
                    'ok' => false,
                    'reason' =>
                        'La autorización del Encargado sin Vo.Bo. no está completa.',
                ];
            }

            return [
                'ok' => true,
                'branch' =>
                    'manager_no_customer_approval',
                'authorized_total' =>
                    $quoteTotal,
            ];
        }

        return [
            'ok' => false,
            'reason' =>
                'El estado de autorización post-reparación no es válido.',
        ];
    }


    protected function canPreparePostRepairCollectionDelivery(): bool
    {
        $record =
            $this->record
            ?? null;

        if (
            ! $record
            || ! auth()->check()
        ) {
            return false;
        }

        if (
            ! ServiceAccess::hasServiceRole([
                'Servicio - Encargado de Técnicos',
                'Servicio - Supervisor',
            ])
        ) {
            return false;
        }

        if (
            ! ServiceAccess::can(
                'service.repairs.update'
            )
        ) {
            return false;
        }

        if (
            (string) (
                $record->status
                ?? ''
            ) !== 'recibido'
            || (string) (
                $record->workflow_stage
                ?? ''
            ) !== 'quote_draft'
        ) {
            return false;
        }

        if (
            ! empty(
                $record->
                    account_receivable_id
            )
            || ! empty(
                $record->
                    ready_for_delivery_at
            )
            || ! empty(
                $record->
                    delivered_at
            )
            || ! empty(
                $record->
                    economic_closed_at
            )
        ) {
            return false;
        }

        $economicStatus =
            trim(
                (string) (
                    $record->
                        economic_status
                    ?? ''
                )
            );

        if ($economicStatus !== '') {
            return false;
        }

        $metadata =
            $this->
                postRepairMetadataArray(
                    $record->
                        metadata
                    ?? null
                );

        if (
            data_get(
                $metadata,
                'post_repair_collection_delivery'
            ) !== null
        ) {
            return false;
        }

        $authorization =
            $this->
                postRepairCollectionAuthorizationForRecord(
                    $record,
                    $metadata
                );

        if (
            ! (
                $authorization[
                    'ok'
                ]
                ?? false
            )
        ) {
            return false;
        }

        if (
            ! \Illuminate\Support\Facades\Schema::
                hasTable(
                    'account_receivables'
                )
        ) {
            return false;
        }

        return
            ! \Illuminate\Support\Facades\DB::
                table(
                    'account_receivables'
                )
                ->where(
                    'source_type',
                    'service_repair_order'
                )
                ->where(
                    'source_id',
                    (int) $record->getKey()
                )
                ->exists();
    }


    protected function managerReviewCostingPending(): bool
    {
        $record =
            $this->record;

        if (
            ! $record
            || ! \App\Support\Service\ServiceAccess::
                hasServiceRole(
                    'Servicio - Encargado de Técnicos'
                )
            || \App\Support\Service\ServiceAccess::
                hasServiceRole(
                    'Servicio - Supervisor'
                )
        ) {
            return false;
        }

        if (
            (string) (
                $record->
                    workflow_stage
                ?? ''
            ) !== 'quote_draft'
            || (string) (
                $record->status
                ?? ''
            ) !== 'recibido'
        ) {
            return false;
        }

        $metadata =
            $record->metadata
            ?? [];

        if (
            is_string(
                $metadata
            )
        ) {
            $metadata =
                json_decode(
                    $metadata,
                    true
                )
                ?: [];
        }

        if (
            ! is_array(
                $metadata
            )
        ) {
            return false;
        }

        $technical =
            $metadata[
                'technical_work'
            ]
            ?? [];

        if (
            ! is_array(
                $technical
            )
            || (
                $technical[
                    'status'
                ]
                ?? null
            ) !== 'completed'
        ) {
            return false;
        }

        $review =
            $metadata[
                'manager_review'
            ]
            ?? [];

        return ! (
            is_array(
                $review
            )
            && (
                $review[
                    'status'
                ]
                ?? null
            ) === 'completed'
        );
    }



    /*
     * BEXIA_ATC_RECEPTION_REPAIR_READONLY_GUARD_V5_83_4C5E5
     */
    protected function isReceptionRepairReadOnlyUser(): bool
    {
        return
            ServiceAccess::hasServiceRole(
                'Servicio - Recepción'
            )
            && ! ServiceAccess::hasServiceRole([
                'Servicio - Encargado de Técnicos',
                'Servicio - Supervisor',
            ]);
    }

    protected function getHeaderActions(): array
    {

        /*
         * BEXIA_ATC_DELIVERY_FROM_TICKET_V5_83_4C5G12
         *
         * La RepairOrder conserva:
         * - revisión y costeo;
         * - Vo.Bo.;
         * - preparación de cobro/entrega.
         *
         * Después del puente, la interfaz operativa
         * de SAL-ATC y entrega vive en ServiceCase.
         *
         * Los métodos backend existentes NO se eliminan.
         */


        /*
         * BEXIA_ATC_MANAGER_REVIEW_ACTIONS_V5_83_4C5C2A1
         *
         * C5C2A1 oculta temporalmente todas las acciones
         * legacy del Encargado.
         *
         * C5C2B agregara:
         * Revisar y costear.
         */
        if (
            ServiceAccess::hasServiceRole(
                'Servicio - Encargado de Técnicos'
            )
            && ! ServiceAccess::hasServiceRole(
                'Servicio - Supervisor'
            )
        ) {
            return [
                $this->managerReviewCostingAction(),
                $this->postRepairCustomerApprovalAction(),
                $this->preparePostRepairNoChargeDeliveryAction(),
                $this->preparePostRepairCollectionDeliveryAction(),
                $this->atcReceivableStatusAction(),

                // BEXIA_ATC_MANAGER_DELIVERY_ACTION_V5_83_4C5E7
                // El Encargado debe poder continuar con la
                // entrega una vez satisfecho canDeliverRepair().
                $this->viewAccountReceivableAction(),
            ];
        }

        /*
         * BEXIA_ATC_TECHNICIAN_HEADER_ONLY_V5_83_4C5B
         *
         * No mostrar al técnico:
         * - cotización;
         * - aprobaciones;
         * - cierre económico;
         * - CxC;
         * - entrega;
         * - recepción;
         * - tracking;
         * - acciones administrativas.
         */
        if (
            ServiceAccess::isRestrictedServiceTechnician()
        ) {
            return [
                $this->
                    finalizeTechnicalWorkAction(),
            ];
        }

        return [
                        $this->postRepairCustomerApprovalAction(),
            $this->preparePostRepairNoChargeDeliveryAction(),
                $this->preparePostRepairCollectionDeliveryAction(),
            ...$this->repairQuoteApprovalHeaderActions(),
$this->atcReceivableStatusAction(),
$this->viewAccountReceivableAction(),
            $this->createAccountReceivableAction(),
            $this->viewEconomicSummaryAction(),
            $this->closeEconomicAction(),

            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('print_reception')
                    ->label('Recepción')
                    ->icon('heroicon-o-printer')
                    ->visible(fn (): bool => $this->canPrintReceptionService())
                    ->url(fn (): string => $this->servicePrintUrl('reception'))
                    ->openUrlInNewTab(),

                \Filament\Actions\Action::make('print_quote')
                    ->label('Presupuesto')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (): bool => $this->canPrintQuoteService())
                    ->url(fn (): string => $this->servicePrintUrl('quote'))
                    ->openUrlInNewTab(),

                \Filament\Actions\Action::make('print_internal_order')
                    ->label('Orden interna')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->visible(fn (): bool => $this->canPrintInternalService())
                    ->url(fn (): string => $this->servicePrintUrl('internal'))
                    ->openUrlInNewTab(),

                \Filament\Actions\Action::make('print_solution')
                    ->label('Solución')
                    ->icon('heroicon-o-document-check')
                    ->visible(fn (): bool => $this->canPrintSolutionService())
                    ->url(fn (): string => $this->servicePrintUrl('solution'))
                    ->openUrlInNewTab(),

                \Filament\Actions\Action::make('print_delivery')
                    ->label('Entrega')
                    ->icon('heroicon-o-truck')
                    ->visible(fn (): bool => $this->canPrintDeliveryService())
                    ->url(fn (): string => $this->servicePrintUrl('delivery'))
                    ->openUrlInNewTab(),
            ])
                ->label('Impresiones')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->button(),

            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('public_tracking_link')
                    ->label('Enlace')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading('Enlace público de seguimiento')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->visible(fn (): bool => ServiceAccess::canManageRepairPublicTracking()
                        && $this->canUsePublicTracking('service.repairs.public_tracking.view')
                        && (
                            ! $this->recordHasPublicTrackingTokenForRecord($this->record)
                            || $this->publicTrackingEnabledForRecord($this->record)
                        ))
                    ->modalContent(fn (): \Illuminate\Contracts\View\View => view('filament.actions.service-public-tracking-link', [
                        'url' => $this->publicTrackingUrlForRecord($this->record),
                    ])),

                \Filament\Actions\Action::make('regenerate_public_tracking_link')
                    ->label('Regenerar enlace')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerar enlace público')
                    ->modalDescription('El enlace anterior dejará de funcionar. El cliente solo podrá consultar el nuevo enlace.')
                    ->visible(fn (): bool => ServiceAccess::canManageRepairPublicTracking()
                        && $this->canUsePublicTracking('service.repairs.public_tracking.regenerate')
                        && $this->recordHasPublicTrackingTokenForRecord($this->record))
                    ->action(function (): void {
                        $this->regeneratePublicTrackingTokenForRecord($this->record);

                        \Filament\Notifications\Notification::make()
                            ->title('Enlace público regenerado')
                            ->body('El enlace anterior fue invalidado.')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('disable_public_tracking_link')
                    ->label('Desactivar enlace')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar enlace público')
                    ->modalDescription('La URL pública dejará de estar disponible para el cliente.')
                    ->visible(fn (): bool => ServiceAccess::canManageRepairPublicTracking()
                        && $this->canUsePublicTracking('service.repairs.public_tracking.disable')
                        && $this->recordHasPublicTrackingTokenForRecord($this->record)
                        && $this->publicTrackingEnabledForRecord($this->record))
                    ->action(function (): void {
                        $this->setPublicTrackingEnabledForRecord($this->record, false);

                        \Filament\Notifications\Notification::make()
                            ->title('Enlace público desactivado')
                            ->body('El cliente ya no podrá consultar esta URL.')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('enable_public_tracking_link')
                    ->label('Activar enlace')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activar enlace público')
                    ->modalDescription('La URL pública volverá a estar disponible para el cliente.')
                    ->visible(fn (): bool => ServiceAccess::canManageRepairPublicTracking()
                        && $this->canUsePublicTracking('service.repairs.public_tracking.disable')
                        && $this->recordHasPublicTrackingTokenForRecord($this->record)
                        && ! $this->publicTrackingEnabledForRecord($this->record))
                    ->action(function (): void {
                        $this->setPublicTrackingEnabledForRecord($this->record, true);

                        \Filament\Notifications\Notification::make()
                            ->title('Enlace público activado')
                            ->success()
                            ->send();
                    }),
            ])
                ->button()
                ->label('Enlaces')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn (): bool => ServiceAccess::canManageRepairPublicTracking()
                        && $this->canUsePublicTracking('service.repairs.public_tracking.view')
                    || ServiceAccess::canManageRepairPublicTracking()
                        && $this->canUsePublicTracking('service.repairs.public_tracking.regenerate')
                    || ServiceAccess::canManageRepairPublicTracking()
                        && $this->canUsePublicTracking('service.repairs.public_tracking.disable')),

            \Filament\Actions\Action::make('capture_reception_signature')
                ->label('Firmar recepción')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->modalHeading('Firma digital de recepción')
                ->modalSubmitActionLabel('Guardar firma de recepción')
                // BEXIA_ATC_RECEPTION_SIGNATURE_LIFECYCLE_V5_83_4C5E6
                //
                // La firma de recepción solamente pertenece a la
                // etapa de recepción física.
                //
                // Si RepairOrder.received_at ya existe, el equipo
                // ya fue recibido y esta acción no debe reaparecer
                // aunque la firma utilizada provenga de recolección.
                ->visible(fn (): bool =>
                    ServiceAccess::canCaptureRepairReception()
                    && blank($this->record?->received_at)
                    && ! $this->repairHasAttachmentStage(
                        $this->record,
                        'reception_signature'
                    )
                )
                ->form([
                    \Filament\Forms\Components\TextInput::make('received_from')
                        ->label('Nombre de quien entrega')
                        ->helperText('Persona que entrega el equipo/producto para diagnóstico o reparación.')
                        ->required()
                        ->maxLength(255),

                    \Filament\Forms\Components\Select::make(
                        'reception_physical_condition'
                    )
                        ->label('Estado físico al recibir')
                        ->options(
                            \App\Support\Service\ServiceRepairReceptionChecklistService::PHYSICAL_CONDITIONS
                        )
                        ->native(false)
                        ->required(),

                    \Filament\Forms\Components\Select::make(
                        'reception_power_status'
                    )
                        ->label('Prueba de encendido')
                        ->options(
                            \App\Support\Service\ServiceRepairReceptionChecklistService::POWER_STATUSES
                        )
                        ->native(false)
                        ->required(),

                    \Filament\Forms\Components\CheckboxList::make(
                        'reception_accessories'
                    )
                        ->label('Accesorios recibidos')
                        ->options(
                            \App\Support\Service\ServiceRepairReceptionChecklistService::ACCESSORIES
                        )
                        ->columns(2)
                        ->required()
                        ->minItems(1)
                        ->live()
                        ->columnSpanFull(),

                    \Filament\Forms\Components\TextInput::make(
                        'reception_accessories_other'
                    )
                        ->label('Otro accesorio recibido')
                        ->helperText(
                            'Describe cualquier accesorio que no aparezca en la lista.'
                        )
                        ->required(
                            fn (
                                \Filament\Forms\Get $get
                            ): bool =>
                                in_array(
                                    'otro',
                                    (array) $get(
                                        'reception_accessories'
                                    ),
                                    true
                                )
                        )
                        ->visible(
                            fn (
                                \Filament\Forms\Get $get
                            ): bool =>
                                in_array(
                                    'otro',
                                    (array) $get(
                                        'reception_accessories'
                                    ),
                                    true
                                )
                        )
                        ->maxLength(500)
                        ->columnSpanFull(),

                    \Filament\Forms\Components\CheckboxList::make(
                        'reception_confirmations'
                    )
                        ->label('Checklist obligatorio')
                        ->options(
                            \App\Support\Service\ServiceRepairReceptionChecklistService::CONFIRMATIONS
                        )
                        ->helperText(
                            'Los cuatro puntos deben confirmarse antes de firmar la recepción.'
                        )
                        ->required()
                        ->minItems(4)
                        ->maxItems(4)
                        ->columns(1)
                        ->columnSpanFull(),

                    \Filament\Forms\Components\Textarea::make(
                        'reception_notes'
                    )
                        ->label('Observaciones de recepción')
                        ->helperText(
                            'Describe golpes, rayones, faltantes, condición especial o cualquier detalle relevante.'
                        )
                        ->rows(4)
                        ->required()
                        ->columnSpanFull(),

                    \Filament\Forms\Components\FileUpload::make('reception_files')
                        ->label('Evidencia de recepción')
                        ->helperText('Opcional: sube foto del equipo recibido, accesorios, estado físico o documento relacionado. Primero sube la evidencia y al final firma.')
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/plain',
                            'text/csv',
                        ])
                        ->disk('public')
                        ->directory('service/reception-files')
                        ->multiple()
                        ->reorderable()
                        ->downloadable()
                        ->openable()
                        ->imagePreviewHeight('120')
                        ->maxFiles(10)
                        ->maxSize(10240)
                        ->columnSpanFull(),

                    \Filament\Forms\Components\ViewField::make('reception_signature_data')
                        ->label('Firma digital de recepción')
                        ->view('filament.forms.components.signature-pad')
                        ->required()
                        ->dehydrated(true)
                        ->helperText('Firma al final, después de subir la evidencia, para evitar que el upload reinicie el recuadro.')
                        ->validationMessages([
                            'required' => 'Captura la firma digital de quien entrega.',
                        ])
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->captureRepairReceptionSignature($data);
                }),


















            \Filament\Actions\Action::make('stage_send_quote_to_approval')
                ->label('Enviar a aprobacion')
                    ->hidden(fn (\App\Models\RepairOrder $record): bool => ! \App\Support\Service\ServiceAccess::canSendRepairQuoteToApproval($record))
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Hidden::make('approval_workflow_id')
                        ->default(fn ($record): ?int => $record && ($workflow = \App\Support\Service\ServiceAccess::resolveServiceApprovalWorkflowForRepair($record, 'service_repair_quote_internal')) ? (int) $workflow->id : null),
                    \Filament\Forms\Components\Hidden::make('internal_approval_flow_id')
                        ->default(fn ($record): ?int => $record && ($workflow = \App\Support\Service\ServiceAccess::resolveServiceApprovalWorkflowForRepair($record, 'service_repair_quote_internal')) ? (int) $workflow->id : null),
                    \Filament\Forms\Components\Hidden::make('workflow_id')
                        ->default(fn ($record): ?int => $record && ($workflow = \App\Support\Service\ServiceAccess::resolveServiceApprovalWorkflowForRepair($record, 'service_repair_quote_internal')) ? (int) $workflow->id : null),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Notas de envio')
                        ->rows(3),
                ])
                ->visible(function (): bool {
                    $record = $this->record;

                    if ((string) ($record->workflow_stage ?: 'quote_draft') !== 'quote_draft') {
                        return false;
                    }

                    return ServiceAccess::canSubmitRepairQuote($record);
                })
                ->action(function (array $data): void {

                $record = $this->record;

                if (! $record) {
                    \Filament\Notifications\Notification::make()
                        ->title('No se encontró la reparación')
                        ->body('Recarga la pantalla e intenta nuevamente.')
                        ->danger()
                        ->send();

                    return;
                }

                $autoApprovalWorkflow = \App\Support\Service\ServiceAccess::resolveServiceApprovalWorkflowForRepair($record, 'service_repair_quote_internal');

                if (! $autoApprovalWorkflow) {
                    \Filament\Notifications\Notification::make()
                        ->title('No hay flujo de aprobación aplicable')
                        ->body('Configura un flujo activo con pasos para Presupuesto de reparación / servicio.')
                        ->danger()
                        ->send();

                    return;
                }

                $data['approval_workflow_id'] = $autoApprovalWorkflow->id;
                $data['internal_approval_flow_id'] = $autoApprovalWorkflow->id;
                $data['workflow_id'] = $autoApprovalWorkflow->id;

                    $record = $this->record;
                    $documentType = (string) ($data['document_type'] ?? 'service_repair_quote_internal');

                    if (! \App\Support\Service\ServiceAccess::hasActiveServiceApprovalWorkflowForRepair($record, $documentType)) {
                        \Filament\Notifications\Notification::make()
                            ->title('No hay flujo configurado')
                            ->body('Configura un flujo activo para este tipo de aprobacion antes de enviar.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $approvalRequestId = null;

                    if (method_exists(\App\Support\Service\ServiceAccess::class, 'createInternalApprovalRequestForRepair')) {
                        $approvalRequestId = \App\Support\Service\ServiceAccess::createInternalApprovalRequestForRepair(
                            $record,
                            $documentType,
                            $data['notes'] ?? null
                        );
                    }

                    if (! $approvalRequestId) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo crear la aprobacion')
                            ->body('Revisa que el flujo tenga pasos activos y aplique por empresa/monto.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->update([
                        'workflow_stage' => 'pending_approval',
                        'status' => 'pending_approval',
                        'quote_status' => 'pending_internal',
                        'quote_submitted_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Enviada a aprobacion')
                        ->body('La cotizacion quedo pendiente de aprobacion.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            \Filament\Actions\Action::make('stage_record_customer_decision')
                ->label('Registrar VoBo del cliente')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Registrar respuesta del cliente')
                ->modalDescription(
                    'Registra si el cliente autorizó o rechazó la cotización y el medio por el que confirmó.'
                )
                ->modalSubmitActionLabel(
                    'Registrar respuesta'
                )
                ->visible(
                    fn (): bool =>
                        ServiceAccess::canRecordRepairCustomerDecision(
                            $this->record
                        )
                )
                ->form([
                    \Filament\Forms\Components\Radio::make(
                        'customer_decision'
                    )
                        ->label('Respuesta del cliente')
                        ->options(
                            \App\Support\Service\ServiceRepairCustomerDecisionService::DECISIONS
                        )
                        ->required()
                        ->live(),

                    \Filament\Forms\Components\Select::make(
                        'customer_decision_channel'
                    )
                        ->label('Medio de confirmación')
                        ->options(
                            \App\Support\Service\ServiceRepairCustomerDecisionService::CHANNELS
                        )
                        ->default('whatsapp')
                        ->native(false)
                        ->required(),

                    \Filament\Forms\Components\DateTimePicker::make(
                        'customer_decision_at'
                    )
                        ->label('Fecha y hora de respuesta')
                        ->default(now())
                        ->seconds(false)
                        ->required(),

                    \Filament\Forms\Components\Textarea::make(
                        'customer_decision_notes'
                    )
                        ->label('Observaciones')
                        ->helperText(
                            'Ejemplo: Cliente confirma por WhatsApp que autoriza la reparación por el importe cotizado.'
                        )
                        ->rows(4)
                        ->required()
                        ->columnSpanFull(),

                    \Filament\Forms\Components\FileUpload::make(
                        'customer_decision_files'
                    )
                        ->label(
                            'Evidencia de la respuesta (opcional)'
                        )
                        ->helperText(
                            'Puedes adjuntar captura de WhatsApp, correo, foto o documento.'
                        )
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'application/pdf',
                            'text/plain',
                        ])
                        ->disk('public')
                        ->directory(
                            'service/customer-quote-decisions'
                        )
                        ->multiple()
                        ->reorderable()
                        ->downloadable()
                        ->openable()
                        ->imagePreviewHeight('120')
                        ->maxFiles(5)
                        ->maxSize(10240)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;

                    $decision = (string) (
                        $data['customer_decision'] ?? ''
                    );

                    $channel = (string) (
                        $data[
                            'customer_decision_channel'
                        ] ?? ''
                    );

                    $notes = trim((string) (
                        $data[
                            'customer_decision_notes'
                        ] ?? ''
                    ));

                    $record = app(
                        \App\Support\Service\ServiceRepairCustomerDecisionService::class
                    )->recordDecision(
                        $record,
                        $data
                    );

                    $files = (array) (
                        $data[
                            'customer_decision_files'
                        ] ?? []
                    );

                    if ($files !== []) {
                        $approved =
                            $decision === 'approved';

                        $channelLabel =
                            \App\Support\Service\ServiceRepairCustomerDecisionService::CHANNELS[
                                $channel
                            ] ?? $channel;

                        $this->saveServiceStageFilesForRepair(
                            record: $record,
                            paths: $files,
                            stage: $approved
                                ? 'customer_quote_approval'
                                : 'customer_quote_rejection',
                            notes: (
                                $approved
                                    ? 'Evidencia VoBo cliente'
                                    : 'Evidencia rechazo cliente'
                            )
                                . ' - '
                                . $channelLabel
                                . ' - '
                                . $notes,
                            eventType:
                                'customer_quote_decision_evidence_uploaded',
                            eventDescription:
                                'Se agregó evidencia de la respuesta del cliente a la cotización.'
                        );
                    }

                    if ($decision === 'approved') {
                        \Filament\Notifications\Notification::make()
                            ->title(
                                'VoBo del cliente registrado'
                            )
                            ->body(
                                'La cotización quedó autorizada y la reparación puede continuar.'
                            )
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title(
                                'Cliente no autorizó la cotización'
                            )
                            ->body(
                                'La cotización regresó a borrador para poder revisarla o modificarla.'
                            )
                            ->warning()
                            ->send();
                    }

                    $this->redirect(
                        $this->getResource()::getUrl(
                            'edit',
                            ['record' => $record]
                        )
                    );
                }),

            \Filament\Actions\Action::make('stage_start_repair')
                ->label('Tomar / iniciar reparacion')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'quote_approved'
                    && ServiceAccess::canWorkRepair($this->record))
                ->action(function (): void {
                    $record = $this->record;

                    $record->update([
                        'workflow_stage' => 'in_repair',
                        'status' => 'in_repair',
                        'repair_started_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Reparacion iniciada')
                        ->body('Ahora se puede capturar resolucion final y tiempos reales.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            \Filament\Actions\Action::make('stage_mark_repaired')
                ->label('Marcar reparado')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Textarea::make('resolution')
                        ->label('Resolucion / trabajo realizado')
                        ->required()
                        ->rows(4),

                    \Filament\Forms\Components\FileUpload::make('solution_files')
                        ->label('Evidencia obligatoria de la solución')
                        ->required()
                        ->minFiles(1)
                        ->validationMessages([
                            'required' => 'Agrega al menos una foto o archivo de evidencia de la solución.',
                            'min' => 'Agrega al menos una foto o archivo de evidencia de la solución.',
                        ])
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/plain',
                            'text/csv',
                        ])
                        ->helperText('Obligatorio: sube al menos una foto, PDF, Word, Excel, TXT o CSV como evidencia del trabajo realizado.')
                        ->disk('public')
                        ->directory('service/solution-files')
                        ->multiple()
                        ->reorderable()
                        ->downloadable()
                        ->openable()
                        ->imagePreviewHeight('120')
                        ->maxFiles(10)
                        ->maxSize(10240),

                ])
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'in_repair'
                    && ServiceAccess::canWorkRepair($this->record))
                ->action(function (array $data): void {
                    $record = $this->record;

                    $record->update([
                        'workflow_stage' => 'repaired',
                        'status' => 'repaired',
                        'resolution' => $data['resolution'] ?? $record->resolution,
                        'repair_finished_at' => now(),
                    ]);

                    $this->saveSolutionFilesForRepair($record, (array) ($data['solution_files'] ?? []));

                    \Filament\Notifications\Notification::make()
                        ->title('Reparacion marcada como reparada')
                        ->body('Siguiente paso: enviar a revision de supervisor.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            \Filament\Actions\Action::make('stage_send_supervisor_review')
                ->label('Enviar a revisión supervisor')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Enviar a revisión de supervisor')
                ->modalDescription(
                    'La reparación quedará pendiente de la revisión final del supervisor.'
                )
                ->visible(
                    fn (): bool => $this->canSendRepairToSupervisorReview()
                )
                ->action(function (): void {
                    $record = $this->record;

                    if (! $this->canSendRepairToSupervisorReview()) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se puede enviar a revisión')
                            ->body(
                                'La reparación debe estar reparada y tu usuario debe ser el técnico asignado, encargado o supervisor autorizado.'
                            )
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->update([
                        'workflow_stage' => 'supervisor_review',
                        'status' => 'supervisor_review',
                        'supervisor_review_requested_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Enviada a revisión')
                        ->body(
                            'La reparación queda pendiente de revisión del supervisor.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        $this->getResource()::getUrl(
                            'edit',
                            ['record' => $record]
                        )
                    );
                }),

            \Filament\Actions\Action::make('stage_approve_supervisor_review')
                ->label('Aprobar revisión')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar revisión de supervisor')
                ->modalDescription(
                    'Confirma que la reparación fue revisada y puede pasar a entrega.'
                )
                ->visible(
                    fn (): bool =>
                        (string) ($this->record->workflow_stage ?: '') === 'supervisor_review'
                        && \App\Support\Service\ServiceAccess::can(
                            'service.repairs.supervisor_review.approve'
                        )
                )
                ->action(function (): void {
                    $record = $this->record;

                    if (
                        ! $record
                        || (string) ($record->workflow_stage ?: '') !== 'supervisor_review'
                        || ! \App\Support\Service\ServiceAccess::can(
                            'service.repairs.supervisor_review.approve'
                        )
                    ) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se puede aprobar la revisión')
                            ->body(
                                'La reparación debe estar en revisión de supervisor y tu usuario debe tener permiso de aprobación.'
                            )
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->update([
                        'workflow_stage' => 'ready_for_delivery',
                        'status' => 'ready_for_delivery',
                        'supervisor_reviewed_at' => now(),
                        'ready_for_delivery_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Lista para entrega')
                        ->body(
                            'La revisión del supervisor fue aprobada y la reparación ya puede entregarse al cliente.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        $this->getResource()::getUrl(
                            'edit',
                            ['record' => $record]
                        )
                    );
                }),


            \Filament\Actions\DeleteAction::make()
    ->visible(fn (): bool => $this->canDeleteRepairDraftAction()),
        ];
    }



    protected function canSendRepairToSupervisorReview(): bool
    {
        $record = $this->record;
        $user = auth()->user();

        if (! $record || ! $user) {
            return false;
        }

        if (
            (string) ($record->workflow_stage ?? '') !== 'repaired'
            || (string) ($record->status ?? '') !== 'repaired'
        ) {
            return false;
        }

        if (! \App\Support\Service\ServiceAccess::can('service.repairs.work')) {
            return false;
        }

        // Encargado y Supervisor también pueden enviar la reparación a revisión.
        if (
            \App\Support\Service\ServiceAccess::can('service.repairs.quote.submit')
            || \App\Support\Service\ServiceAccess::can(
                'service.repairs.supervisor_review.approve'
            )
        ) {
            return true;
        }

        $employeeId = (int) ($record->assigned_employee_id ?? 0);

        if (
            $employeeId <= 0
            || ! \Illuminate\Support\Facades\Schema::hasTable('employees')
        ) {
            return false;
        }

        $employee = \Illuminate\Support\Facades\DB::table('employees')
            ->where('id', $employeeId)
            ->first();

        if (! $employee) {
            return false;
        }

        if (
            \Illuminate\Support\Facades\Schema::hasColumn(
                'employees',
                'user_id'
            )
            && (int) ($employee->user_id ?? 0) === (int) $user->id
        ) {
            return true;
        }

        $userEmail = mb_strtolower(
            trim((string) ($user->email ?? ''))
        );

        if ($userEmail === '') {
            return false;
        }

        foreach (['work_email', 'email'] as $column) {
            if (
                ! \Illuminate\Support\Facades\Schema::hasColumn(
                    'employees',
                    $column
                )
            ) {
                continue;
            }

            $employeeEmail = mb_strtolower(
                trim((string) ($employee->{$column} ?? ''))
            );

            if (
                $employeeEmail !== ''
                && $employeeEmail === $userEmail
            ) {
                return true;
            }
        }

        return false;
    }


    protected function saveSolutionFilesForRepair(object $record, array $paths): void
    {
        $this->saveServiceStageFilesForRepair(
            record: $record,
            paths: $paths,
            stage: 'solution',
            notes: 'Evidencia de solución',
            eventType: 'solution_files_uploaded',
            eventDescription: 'Se agregaron fotos y documentos de evidencia de la solución.'
        );
    }


    protected function canUsePublicTracking(string $permission): bool
    {
        return \App\Support\Service\ServiceAccess::can($permission);
    }

    protected function recordHasPublicTrackingTokenForRecord(object $record): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_token')) {
            return false;
        }

        $repairId = $record->getKey();

        if (! $repairId) {
            return false;
        }

        return filled(\Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $repairId)
            ->value('public_tracking_token'));
    }

    protected function publicTrackingEnabledForRecord(object $record): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_enabled')) {
            return true;
        }

        $repairId = $record->getKey();

        if (! $repairId) {
            return false;
        }

        return (bool) \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $repairId)
            ->value('public_tracking_enabled');
    }

    protected function regeneratePublicTrackingTokenForRecord(object $record): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_token')) {
            return null;
        }

        $repairId = $record->getKey();

        if (! $repairId) {
            return null;
        }

        do {
            $token = \Illuminate\Support\Str::random(48);
            $exists = \Illuminate\Support\Facades\DB::table('repair_orders')
                ->where('public_tracking_token', $token)
                ->where('id', '!=', $repairId)
                ->exists();
        } while ($exists);

        $payload = [
            'public_tracking_token' => $token,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_enabled')) {
            $payload['public_tracking_enabled'] = true;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_token_created_at')) {
            $payload['public_tracking_token_created_at'] = now();
        }

        \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $repairId)
            ->update($payload);

        $record->public_tracking_token = $token;
        $record->public_tracking_enabled = true;

        $this->logPublicTrackingEventForRecord(
            $record,
            'public_tracking_regenerated',
            'Se regeneró el enlace público de seguimiento.'
        );

        return $token;
    }

    protected function setPublicTrackingEnabledForRecord(object $record, bool $enabled): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_enabled')) {
            return;
        }

        $repairId = $record->getKey();

        if (! $repairId) {
            return;
        }

        if ($enabled && ! $this->recordHasPublicTrackingTokenForRecord($record)) {
            $this->regeneratePublicTrackingTokenForRecord($record);

            return;
        }

        \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $repairId)
            ->update([
                'public_tracking_enabled' => $enabled,
            ]);

        $record->public_tracking_enabled = $enabled;

        $this->logPublicTrackingEventForRecord(
            $record,
            $enabled ? 'public_tracking_enabled' : 'public_tracking_disabled',
            $enabled
                ? 'Se activó el enlace público de seguimiento.'
                : 'Se desactivó el enlace público de seguimiento.'
        );
    }

    protected function logPublicTrackingEventForRecord(object $record, string $eventType, string $notes): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('service_case_events')) {
            return;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('service_case_events');

        $payload = [
            'event_type' => $eventType,
            'notes' => $notes,
        ];

        $map = [
            'company_id' => $record->company_id ?? null,
            'service_case_id' => $record->service_case_id ?? null,
            'repair_order_id' => $record->getKey(),
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($map as $column => $value) {
            if (in_array($column, $columns, true)) {
                $payload[$column] = $value;
            }
        }

        $safe = [];

        foreach ($payload as $column => $value) {
            if (in_array($column, $columns, true)) {
                $safe[$column] = $value;
            }
        }

        if ($safe !== []) {
            \Illuminate\Support\Facades\DB::table('service_case_events')->insert($safe);
        }
    }

    protected function ensurePublicTrackingTokenForRecord(object $record): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_token')) {
            return null;
        }

        $token = (string) ($record->public_tracking_token ?? '');

        if ($token !== '') {
            return $token;
        }

        $repairId = $record->getKey();

        if (! $repairId) {
            return null;
        }

        do {
            $token = \Illuminate\Support\Str::random(48);
            $exists = \Illuminate\Support\Facades\DB::table('repair_orders')
                ->where('public_tracking_token', $token)
                ->exists();
        } while ($exists);

        $payload = [
            'public_tracking_token' => $token,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_enabled')) {
            $payload['public_tracking_enabled'] = true;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('repair_orders', 'public_tracking_token_created_at')) {
            $payload['public_tracking_token_created_at'] = now();
        }

        \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $repairId)
            ->update($payload);

        $record->public_tracking_token = $token;
        $record->public_tracking_enabled = true;

        $this->logPublicTrackingEventForRecord(
            $record,
            'public_tracking_created',
            'Se generó el enlace público de seguimiento.'
        );

        return $token;
    }

    protected function publicTrackingUrlForRecord(object $record): string
    {
        $token = $this->ensurePublicTrackingTokenForRecord($record);

        if (! $token) {
            return 'No disponible';
        }

        return route('public.service.tracking.show', ['token' => $token]);
    }

    protected function saveReceptionFilesForRepair(object $record, array $paths): void
    {
        $this->saveServiceStageFilesForRepair(
            record: $record,
            paths: $paths,
            stage: 'reception',
            notes: 'Evidencia de recepción',
            eventType: 'reception_files_uploaded',
            eventDescription: 'Se agregaron fotos y documentos de evidencia de recepción.'
        );
    }

    protected function saveDeliveryFilesForRepair(object $record, array $paths): void
    {
        $this->saveServiceStageFilesForRepair(
            record: $record,
            paths: $paths,
            stage: 'delivery',
            notes: 'Evidencia de entrega',
            eventType: 'delivery_files_uploaded',
            eventDescription: 'Se agregaron fotos y documentos de evidencia de entrega.'
        );
    }

    protected function saveDeliverySignatureForRepair(object $record, mixed $signatureData): void
    {
        if (! is_string($signatureData) || trim($signatureData) === '') {
            return;
        }

        if (! str_starts_with($signatureData, 'data:image/png;base64,')) {
            return;
        }

        $encoded = substr($signatureData, strlen('data:image/png;base64,'));
        $binary = base64_decode($encoded, true);

        if ($binary === false || $binary === '') {
            return;
        }

        $folio = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($record->folio ?? ('repair_' . $record->getKey())));
        $filePath = 'service/signatures/delivery/' . $folio . '-' . now()->format('YmdHis') . '.png';

        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $binary);

        $this->saveServiceStageFilesForRepair(
            record: $record,
            paths: [$filePath],
            stage: 'delivery_signature',
            notes: 'Firma digital de entrega',
            eventType: 'delivery_signature_captured',
            eventDescription: 'Se capturó firma digital de entrega.'
        );
    }

    protected function saveReceptionSignatureForRepair(object $record, mixed $signatureData, ?string $receivedFrom = null, ?string $receptionNotes = null): void
    {
        if (! is_string($signatureData) || trim($signatureData) === '') {
            return;
        }

        if (! str_starts_with($signatureData, 'data:image/png;base64,')) {
            return;
        }

        $encoded = substr($signatureData, strlen('data:image/png;base64,'));
        $binary = base64_decode($encoded, true);

        if ($binary === false || $binary === '') {
            return;
        }

        $folio = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($record->folio ?? ('repair_' . $record->getKey())));
        $filePath = 'service/signatures/reception/' . $folio . '-' . now()->format('YmdHis') . '.png';

        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $binary);

        $notes = 'Firma digital de recepción';

        if (filled($receivedFrom)) {
            $notes .= ' - Entrega: ' . trim((string) $receivedFrom);
        }

        if (filled($receptionNotes)) {
            $notes .= ' - Observaciones: ' . trim((string) $receptionNotes);
        }

        $this->saveServiceStageFilesForRepair(
            record: $record,
            paths: [$filePath],
            stage: 'reception_signature',
            notes: $notes,
            eventType: 'reception_signature_captured',
            eventDescription: $notes
        );
    }

    protected function captureRepairReceptionSignature(array $data): void
    {
        $record = $this->record;

        if (! $record) {
            \Filament\Notifications\Notification::make()
                ->title('No se encontró la reparación')
                ->body('Recarga la pantalla e intenta nuevamente.')
                ->danger()
                ->send();

            return;
        }

        if ($this->repairHasAttachmentStage($record, 'reception_signature')) {
            \Filament\Notifications\Notification::make()
                ->title('Recepción ya firmada')
                ->body('Esta reparación ya tiene firma digital de recepción registrada.')
                ->warning()
                ->send();

            return;
        }

        $checklistService = app(
            \App\Support\Service\ServiceRepairReceptionChecklistService::class
        );

        /*
         * Validar antes de guardar archivos o firma para
         * no dejar una recepción parcial.
         */
        $checklistService->validateData(
            $data
        );

        $this->saveReceptionFilesForRepair(
            $record,
            (array) (
                $data['reception_files']
                ?? []
            )
        );

        $this->saveReceptionSignatureForRepair(
            $record,
            $data[
                'reception_signature_data'
            ] ?? null,
            $data['received_from'] ?? null,
            $data['reception_notes'] ?? null
        );

        if (
            ! $this->repairHasAttachmentStage(
                $record,
                'reception_signature'
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reception_signature_data' =>
                    'No fue posible guardar la firma de recepción. Intenta nuevamente.',
            ]);
        }

        $record =
            $checklistService->recordChecklist(
                $record,
                $data
            );

        $record->refresh();

        \Filament\Notifications\Notification::make()
            ->title(
                'Recepción registrada'
            )
            ->body(
                'Checklist, condición de recepción y firma quedaron guardados en el expediente.'
            )
            ->success()
            ->send();
    }

    protected function saveServiceStageFilesForRepair(
        object $record,
        array $paths,
        string $stage,
        string $notes,
        string $eventType,
        string $eventDescription
    ): void {
        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            return;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('service_attachments')) {
            return;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('service_attachments');
        $now = now();

        foreach ($paths as $filePath) {
            if (is_array($filePath)) {
                $filePath = $filePath['path'] ?? $filePath['file'] ?? $filePath['name'] ?? null;
            }

            if (! is_string($filePath) || trim($filePath) === '') {
                continue;
            }

            $name = basename($filePath);

            $payload = [
                'company_id' => $record->company_id ?? null,
                'service_case_id' => $record->service_case_id ?? null,
                'repair_order_id' => $record->id ?? null,
                'stage' => $stage,
                'file_path' => $filePath,
                'file_name' => $name,
                'mime_type' => null,
                'is_customer_visible' => false,
                'uploaded_by' => auth()->id(),
                'created_at' => $now,
                'notes' => $notes,
                'updated_at' => $now,
            ];

            $payload = array_intersect_key($payload, array_flip($columns));

            if ($payload !== []) {
                \Illuminate\Support\Facades\DB::table('service_attachments')->insert($payload);
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('service_case_events')) {
            $eventColumns = \Illuminate\Support\Facades\Schema::getColumnListing('service_case_events');

            $event = [
                'company_id' => $record->company_id ?? null,
                'service_case_id' => $record->service_case_id ?? null,
                'repair_order_id' => $record->id ?? null,
                'event_type' => $eventType,
                'notes' => $eventDescription,
                'performed_by' => auth()->id(),
                'performed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $event = array_intersect_key($event, array_flip($eventColumns));

            if ($event !== []) {
                \Illuminate\Support\Facades\DB::table('service_case_events')->insert($event);
            }
        }
    }

    protected function repairHasAttachmentStage(object $record, string $stage): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('service_attachments')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('service_attachments')
            ->where('repair_order_id', $record->getKey())
            ->where('stage', $stage)
            ->exists();
    }



    protected function servicePrintUrl(string $type): string
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        $tenantId = $tenant?->getKey() ?? ($this->record->company_id ?? 1);

        return url('/admin/' . $tenantId . '/service/repair-orders/' . $this->record->getKey() . '/print/' . $type);
    }




    /*
     * BEXIA_ATC_MANAGER_DELIVERY_ACTION_V5_83_4C5E7
     *
     * Acción única de entrega reutilizada por:
     * - Encargado de Técnicos;
     * - flujo administrativo general.
     *
     * La visibilidad sigue dependiendo exclusivamente de
     * ServiceAccess::canDeliverRepair(), incluido el gate
     * post-reparación de pago C5E2.
     *
     * No se relajan permisos ni requisitos:
     * - nombre de quien recibe;
     * - evidencia obligatoria;
     * - firma digital obligatoria.
     */
    /*
     * BEXIA_ATC_REPAIR_EXIT_DOCUMENT_GATE_V5_83_4C5G3
     *
     * Autoriza la salida física ANTES de entregar.
     */
    protected function prepareRepairExitDocumentAction(): Action
    {
        return Action::make(
            'prepare_repair_exit_document'
        )
            ->label(
                'Generar salida de equipo'
            )
            ->icon(
                'heroicon-o-document-check'
            )
            ->color('warning')
            ->modalHeading(
                'Generar documento de salida'
            )
            ->modalDescription(
                'Este documento autoriza la salida física del equipo bajo custodia. No descontará inventario.'
            )
            ->modalSubmitActionLabel(
                'Autorizar salida'
            )
            ->visible(
                fn (): bool =>
                    ServiceAccess::
                        canPrepareRepairExitDocument(
                            $this->record
                        )
                    && ! ServiceAccess::
                        hasAuthorizedRepairExitDocument(
                            $this->record
                        )
            )
            ->form([
                \Filament\Forms\Components\Select::
                    make(
                        'origin_exit_warehouse_id'
                    )
                    ->label(
                        'Ubicación de salida'
                    )
                    ->options(
                        function (): array {
                            return \App\Models\ExitWarehouse::
                                query()
                                ->where(
                                    'company_id',
                                    $this->record->
                                        company_id
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
                                ->orderBy(
                                    'sort_order'
                                )
                                ->orderBy(
                                    'name'
                                )
                                ->pluck(
                                    'name',
                                    'id'
                                )
                                ->all();
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText(
                        'Selecciona el almacén/ubicación configurado en Salidas. Si no existe para esta empresa, captura la ubicación manual.'
                    ),

                \Filament\Forms\Components\TextInput::
                    make(
                        'origin_location_label'
                    )
                    ->label(
                        'Ubicación manual'
                    )
                    ->placeholder(
                        'Sólo si la ubicación no aparece en el catálogo'
                    )
                    ->maxLength(255),

                \Filament\Forms\Components\Textarea::
                    make(
                        'exit_document_notes'
                    )
                    ->label(
                        'Observaciones de salida'
                    )
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(
                function (array $data): void {
                    $repair =
                        app(
                            \App\Support\Service\ServiceRepairExitDocumentService::class
                        )->authorize(
                            $this->record,
                            $data
                        );

                    $this->record =
                        $repair;

                    $this->record->refresh();

                    $document =
                        ServiceAccess::
                            repairExitDocument(
                                $this->record
                            );

                    Notification::make()
                        ->title(
                            'Salida autorizada'
                        )
                        ->body(
                            'Se generó '
                            . (
                                $document[
                                    'folio'
                                ]
                                ?? 'el documento de salida'
                            )
                            . '. Ya puede imprimirse y continuar con la entrega.'
                        )
                        ->success()
                        ->send();
                }
            );
    }

    protected function printRepairExitDocumentAction(): Action
    {
        return Action::make(
            'print_repair_exit_document'
        )
            ->label(
                'Imprimir salida'
            )
            ->icon(
                'heroicon-o-printer'
            )
            ->color('gray')
            ->visible(
                fn (): bool =>
                    ServiceAccess::
                        hasAuthorizedRepairExitDocument(
                            $this->record
                        )
            )
            ->url(
                fn (): string =>
                    route(
                        'service.repair-orders.exit-document',
                        [
                            'tenant' =>
                                $this->record->
                                    company_id,

                            'record' =>
                                $this->record->
                                    getKey(),
                        ]
                    )
            )
            ->openUrlInNewTab();
    }


    protected function deliverToCustomerAction(): Action
    {
        return Action::make(
            'deliver_to_customer'
        )
            ->label(
                'Entregar al cliente'
            )
            ->icon(
                'heroicon-o-truck'
            )
            ->color('success')
            ->modalHeading(
                'Entregar al cliente'
            )
            ->modalSubmitActionLabel(
                'Confirmar entrega'
            )
            ->visible(
                fn (): bool =>
                    ServiceAccess::
                        canDeliverRepair(
                            $this->record
                        )
            )
            ->form([
                \Filament\Forms\Components\TextInput::
                    make(
                        'delivered_to'
                    )
                    ->label(
                        'Nombre de quien recibe'
                    )
                    ->required()
                    ->maxLength(255),

                \Filament\Forms\Components\Textarea::
                    make(
                        'delivery_notes'
                    )
                    ->label(
                        'Observaciones de entrega'
                    )
                    ->rows(4)
                    ->columnSpanFull(),

                \Filament\Forms\Components\FileUpload::
                    make(
                        'delivery_files'
                    )
                    ->label(
                        'Evidencia obligatoria de entrega'
                    )
                    ->required()
                    ->minFiles(1)
                    ->validationMessages([
                        'required' =>
                            'Agrega al menos una foto o archivo de evidencia de entrega.',

                        'min' =>
                            'Agrega al menos una foto o archivo de evidencia de entrega.',
                    ])
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                        'text/csv',
                    ])
                    ->helperText(
                        'Obligatorio: sube una foto del producto entregado, firma física escaneada, acuse o documento relacionado.'
                    )
                    ->disk('public')
                    ->directory(
                        'service/delivery-files'
                    )
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->imagePreviewHeight('120')
                    ->maxFiles(10)
                    ->maxSize(10240)
                    ->columnSpanFull(),

                \Filament\Forms\Components\ViewField::
                    make(
                        'delivery_signature_data'
                    )
                    ->label(
                        'Firma digital de entrega'
                    )
                    ->view(
                        'filament.forms.components.signature-pad'
                    )
                    ->required()
                    ->dehydrated(true)
                    ->helperText(
                        'Firma después de subir la evidencia para evitar que el upload reinicie el recuadro.'
                    )
                    ->validationMessages([
                        'required' =>
                            'Captura la firma digital de quien recibe.',
                    ])
                    ->columnSpanFull(),
            ])
            ->action(
                function (array $data): void {
                    $this->
                        deliverRepairToCustomer(
                            $data
                        );
                }
            );
    }


    protected function markRepairReadyForDelivery(): void
    {
        $record = $this->record;

        if (! $this->repairHasAttachmentStage($record, 'solution')) {
            \Filament\Notifications\Notification::make()
                ->title('Falta evidencia de solución')
                ->body('Para marcar como listo para entrega, primero agrega evidencia al marcar la reparación como reparada.')
                ->danger()
                ->send();

            return;
        }

        $now = now();

        $payload = $this->repairOrderPayloadForExistingColumns([
            'status' => 'ready_for_delivery',
            'workflow_stage' => 'ready_for_delivery',
            'ready_for_delivery_at' => $now,
            'updated_at' => $now,
        ]);

        \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $record->getKey())
            ->update($payload);

        $this->createServiceRepairTransitionEvent(
            $record,
            'repair_ready_for_delivery',
            'La reparación fue marcada como lista para entrega.'
        );

        $record->refresh();

        \Filament\Notifications\Notification::make()
            ->title('Reparación lista para entrega')
            ->success()
            ->send();
    }

    /*
     * BEXIA_ATC_ATOMIC_DELIVERY_V5_83_4C5E8
     *
     * La página ya no cambia estados directamente.
     * Toda la entrega se delega al service transaccional.
     */
    protected function deliverRepairToCustomer(
        array $data
    ): void {
        $record =
            $this->record;

        if (! $record) {
            throw
                \Illuminate\Validation\ValidationException::
                    withMessages([
                        'repair' =>
                            'No se encontró la reparación.',
                    ]);
        }

        $delivered =
            app(
                \App\Support\Service\ServiceRepairDeliveryService::class
            )->deliver(
                $record,
                $data
            );

        $this->record->refresh();

        \Filament\Notifications\Notification::
            make()
            ->title(
                'Reparación entregada'
            )
            ->body(
                'La evidencia y firma quedaron guardadas y el ticket ATC fue cerrado.'
            )
            ->success()
            ->send();

        /*
         * BEXIA_ATC_POST_DELIVERY_REDIRECT_V5_83_4C5E10
         *
         * delivered es estado final.
         * RepairOrderResource::canEdit() exige permiso
         * de reapertura para volver a /edit.
         *
         * El usuario que acaba de entregar no necesita
         * ese permiso para concluir su flujo.
         *
         * Regresar al listado de Reparaciones evita un
         * 403 posterior a una entrega exitosa.
         */
        $this->redirect(
            $this->getResource()::getUrl(
                'index'
            )
        );
    }

    protected function repairOrderPayloadForExistingColumns(array $payload): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('repair_orders')) {
            return [];
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('repair_orders');

        return array_intersect_key($payload, array_flip($columns));
    }

    protected function createServiceRepairTransitionEvent(object $record, string $type, string $description): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('service_case_events')) {
            return;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('service_case_events');

        $payload = [
            'company_id' => $record->company_id ?? null,
            'service_case_id' => $record->service_case_id ?? null,
            'repair_order_id' => $record->getKey(),
            'event_type' => $type,
            'description' => $description,
            'user_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $payload = array_intersect_key($payload, array_flip($columns));

        if ($payload !== []) {
            \Illuminate\Support\Facades\DB::table('service_case_events')->insert($payload);
        }
    }



    protected function repairStageForPrint(): string
    {
        if (! $this->record) {
            return '';
        }

        $stage = \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $this->record->getKey())
            ->value('workflow_stage');

        return (string) ($stage ?? '');
    }

    protected function repairHasQuoteForPrint(): bool
    {
        if (! $this->record) {
            return false;
        }

        $repair = \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $this->record->getKey())
            ->first();

        if (! $repair) {
            return false;
        }

        $quoteTotal = (float) ($repair->quote_total ?? $repair->budget_total ?? $repair->total ?? 0);
        $quoteStatus = (string) ($repair->quote_status ?? '');

        return $quoteTotal > 0
            || in_array($quoteStatus, ['sent', 'submitted', 'approved', 'customer_approved'], true);
    }

    protected function canPrintReceptionService(): bool
    {
        return (bool) $this->record;
    }

    protected function canPrintQuoteService(): bool
    {
        return $this->repairHasQuoteForPrint();
    }

    protected function canPrintInternalService(): bool
    {
        return in_array($this->repairStageForPrint(), [
            'quote_approved',
            'in_repair',
            'repaired',
            'ready_for_delivery',
            'delivered',
        ], true);
    }

    protected function canPrintSolutionService(): bool
    {
        return in_array($this->repairStageForPrint(), [
            'repaired',
            'ready_for_delivery',
            'delivered',
        ], true);
    }

    protected function canPrintDeliveryService(): bool
    {
        return in_array($this->repairStageForPrint(), [
            'ready_for_delivery',
            'delivered',
        ], true);
    }










    protected function canDeleteRepairDraftAction(): bool
    {
        if (! $this->record) {
            return false;
        }

        $repair = \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $this->record->getKey())
            ->first();

        return \App\Support\Service\ServiceAccess::canDeleteRepairDraft($repair);
    }

    protected function closeEconomicAction(): Action
    {
        return Action::make('close_economic')
            ->label('Cierre económico')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Calcular cierre económico')
            ->modalDescription('Calcula refacciones, mano de obra, ganancia, IVA y total final. Si el total supera el presupuesto aprobado, quedará marcado como requiere aprobación.')
            ->visible(fn (): bool => in_array((string) ($this->record->workflow_stage ?: $this->record->status), [
                'repaired',
                'ready_for_delivery',
                'delivered',
            ], true))
            // BEXIA_V582_P7H24F_HIDE_ECONOMIC_CLOSE_AFTER_CLOSED
            ->visible(fn (): bool => $this->canShowEconomicClosureAction()
                && blank($this->record?->economic_closed_at))
            ->action(function (): void {
                $result = ServiceEconomicClosureCalculator::recalculate((int) $this->record->getKey(), [
                    'closed_by' => auth()->id(),
                    'close' => true,
                    'tax_rate' => 16,
                ]);

                $this->record->refresh();

                Notification::make()
                    ->title('Cierre económico calculado')
                    ->body('Total final: $' . number_format((float) ($result['economic_total'] ?? 0), 2) . '. Ganancia total: $' . number_format((float) ($result['total_profit_amount'] ?? 0), 2) . '.')
                    ->success()
                    ->send();
            });
    }

    protected function viewEconomicSummaryAction(): Action
    {
        return Action::make('view_economic_summary')
            ->label('Resumen económico')
            ->icon('heroicon-o-chart-bar-square')
            ->color('gray')
            ->modalWidth('7xl')
            ->modalHeading('Resumen económico de la reparación')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn () => view('filament.service.repair-order-economic-summary', [
                'record' => $this->record,
            ]))
            // BEXIA_ATC_ECONOMIC_SUMMARY_PERMISSION_V5_83_4C5E6
            //
            // El resumen contiene información económica interna.
            // Tener acceso a la reparación no concede por sí solo
            // acceso a esta información.
            ->visible(
                fn (): bool =>
                    ServiceAccess::canManageRepairEconomic(
                        $this->record
                    )
                    && (float) (
                        $this->record->economic_total
                        ?? $this->record->total_amount
                        ?? 0
                    ) > 0
            );
    }

    protected function createAccountReceivableAction(): Action
    {
        return Action::make('create_account_receivable')
            ->label('Crear CxC')
            ->icon('heroicon-o-document-currency-dollar')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Crear cuenta por cobrar')
            ->modalDescription('Se creará una cuenta por cobrar con el total económico de la reparación. Si ya existe una CxC para esta reparación, no se duplicará.')
            ->modalSubmitActionLabel('Crear CxC')
            ->visible(function (): bool {
                if (! ServiceAccess::canManageRepairEconomic($this->record)) {
                    return false;
                }

                $total = (float) ($this->record->total_amount ?? $this->record->economic_total ?? 0);
                $status = (string) ($this->record->economic_status ?? '');

                return $total > 0
                    && in_array($status, ['ready_to_charge'], true)
                    && empty($this->record->account_receivable_id)
                    && ! (bool) ($this->record->economic_requires_approval ?? false);
            })
            ->action(function (): void {
                $result = ServiceReceivableCreator::createForRepairOrder((int) $this->record->getKey(), [
                    'created_by' => auth()->id(),
                ]);

                $this->record->refresh();

                Notification::make()
                    ->title(($result['created'] ?? false) ? 'Cuenta por cobrar creada' : 'Cuenta por cobrar existente')
                    ->body(($result['number'] ?? 'CxC') . ' por $' . number_format((float) ($result['total'] ?? 0), 2))
                    ->success()
                    ->send();
            });
    }


    // BEXIA_ATC_CXC_PERMISSION_BOUNDARY_V5_83_4C5E4
    //
    // ATC puede consultar un resumen operativo del cobro sin
    // abandonar Reparaciones.
    //
    // Esta accion es SOLO LECTURA:
    // - no crea pagos;
    // - no cambia CxC;
    // - no cambia RepairOrder;
    // - no cambia ServiceCase.
    //
    // El acceso directo a "Ver CxC" se controla por el permiso
    // canonico del propio AccountReceivableResource.
    protected function atcReceivableStatusAction(): Action
    {
        return Action::make('atc_receivable_status')
            ->label(
                fn (): string =>
                    'Cobro: '
                    . $this->atcReceivableSnapshot()['payment_label']
            )
            ->icon('heroicon-o-banknotes')
            ->color('info')
            ->modalHeading('Cobro y entrega')
            ->modalDescription(
                'Resumen operativo de solo lectura para ATC. '
                . 'El cobro se registra únicamente desde Cuentas por cobrar '
                . 'por un usuario autorizado.'
            )
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Cerrar')
            ->visible(
                fn (): bool =>
                    (int) (
                        $this->record->account_receivable_id
                        ?? 0
                    ) > 0
            )
            ->form([
                \Filament\Forms\Components\Section::make(
                    'Cuenta por cobrar'
                )
                    ->description(
                        'ATC puede consultar el avance del cobro, '
                        . 'pero no registrar ni modificar pagos desde aquí.'
                    )
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make(
                            'atc_cxc_number'
                        )
                            ->label('CxC')
                            ->content(
                                fn (): string =>
                                    $this->
                                        atcReceivableSnapshot()[
                                            'number'
                                        ]
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'atc_cxc_payment_status'
                        )
                            ->label('Estado de cobro')
                            ->content(
                                fn (): string =>
                                    $this->
                                        atcReceivableSnapshot()[
                                            'payment_label'
                                        ]
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'atc_cxc_total'
                        )
                            ->label('Total')
                            ->content(
                                fn (): string =>
                                    '$'
                                    . number_format(
                                        (float) $this->
                                            atcReceivableSnapshot()[
                                                'total'
                                            ],
                                        2
                                    )
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'atc_cxc_collected'
                        )
                            ->label('Cobrado')
                            ->content(
                                fn (): string =>
                                    '$'
                                    . number_format(
                                        (float) $this->
                                            atcReceivableSnapshot()[
                                                'collected'
                                            ],
                                        2
                                    )
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'atc_cxc_balance'
                        )
                            ->label('Saldo')
                            ->content(
                                fn (): string =>
                                    '$'
                                    . number_format(
                                        (float) $this->
                                            atcReceivableSnapshot()[
                                                'balance'
                                            ],
                                        2
                                    )
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'atc_cxc_policy'
                        )
                            ->label('Política de entrega')
                            ->content(
                                fn (): string =>
                                    $this->
                                        atcReceivableSnapshot()[
                                            'policy_label'
                                        ]
                            ),
                    ])
                    ->columns(2),

                \Filament\Forms\Components\Section::make(
                    'Entrega'
                )
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make(
                            'atc_delivery_status'
                        )
                            ->label('Estado')
                            ->content(
                                fn (): string =>
                                    $this->
                                        atcReceivableSnapshot()[
                                            'delivery_label'
                                        ]
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'atc_delivery_next_step'
                        )
                            ->label('Siguiente paso')
                            ->content(
                                fn (): string =>
                                    $this->
                                        atcReceivableSnapshot()[
                                            'next_step'
                                        ]
                            ),
                    ]),
            ])
            ->action(
                static function (): void {
                    // Solo cierra el modal. No modifica datos.
                }
            );
    }

    protected function atcReceivableSnapshot(): array
    {
        static $cache = [];

        $record = $this->record ?? null;

        if (! $record) {
            return [
                'number' => 'Sin CxC',
                'payment_label' => 'Sin información',
                'total' => 0.0,
                'collected' => 0.0,
                'balance' => 0.0,
                'policy_label' => 'No definida',
                'delivery_label' => 'Pendiente',
                'next_step' => 'No hay una reparación cargada.',
            ];
        }

        $recordId = (int) $record->getKey();

        if (isset($cache[$recordId])) {
            return $cache[$recordId];
        }

        $receivableId = (int) (
            $record->account_receivable_id
            ?? 0
        );

        $receivable = null;

        if (
            $receivableId > 0
            && \Illuminate\Support\Facades\Schema::
                hasTable('account_receivables')
        ) {
            $query =
                \Illuminate\Support\Facades\DB::
                    table('account_receivables')
                    ->where('id', $receivableId);

            if (
                \Illuminate\Support\Facades\Schema::
                    hasColumn(
                        'account_receivables',
                        'company_id'
                    )
                && (int) ($record->company_id ?? 0) > 0
            ) {
                $query->where(
                    'company_id',
                    (int) $record->company_id
                );
            }

            $receivable = $query->first();
        }

        $total = (float) (
            $receivable->total
            ?? $record->economic_total
            ?? $record->total_amount
            ?? 0
        );

        $collected = (float) (
            $receivable->collected_total
            ?? 0
        );

        $balance = (float) (
            $receivable->balance_total
            ?? max(
                0,
                $total - $collected
            )
        );

        $repairPaymentStatus = (string) (
            $record->economic_payment_status
            ?? ''
        );

        $paymentLabel = 'Pendiente de cobro';

        if ($repairPaymentStatus === 'paid') {
            $paymentLabel = 'Pagado';
        } elseif (
            $repairPaymentStatus === 'partial'
            || $collected > 0.0001
        ) {
            $paymentLabel = 'Cobro parcial';
        }

        $receivableLooksFullyPaid =
            $receivable !== null
            && $total > 0
            && $balance <= 0.0001
            && $collected >= ($total - 0.0001);

        if (
            $repairPaymentStatus !== 'paid'
            && $receivableLooksFullyPaid
        ) {
            $paymentLabel =
                'Cobro completo · sincronización pendiente';
        }

        $metadata = $record->metadata ?? [];

        if (is_string($metadata)) {
            $decoded = json_decode(
                $metadata,
                true
            );

            $metadata =
                is_array($decoded)
                ? $decoded
                : [];
        }

        $bridge =
            is_array($metadata)
            ? (
                $metadata[
                    'post_repair_collection_delivery'
                ]
                ?? []
            )
            : [];

        $policy = (string) (
            $bridge['collection_policy']
            ?? ''
        );

        $policyLabel = match ($policy) {
            'payment_before_delivery' =>
                'Cobrar antes de salir a entrega',

            'payment_on_delivery' =>
                'Cobrar al momento de entregar',

            'credit_allowed' =>
                'Crédito autorizado / saldo permitido',

            'payment_required' =>
                'Pago requerido antes de entregar (legacy)',

            default =>
                'No definida',
        };

        $delivered =
            ! empty($record->delivered_at)
            || in_array(
                (string) (
                    $record->workflow_stage
                    ?? ''
                ),
                ['delivered'],
                true
            )
            || in_array(
                (string) (
                    $record->status
                    ?? ''
                ),
                ['delivered', 'entregado'],
                true
            );

        $deliveryLabel = 'Pendiente';

        if ($delivered) {
            $deliveryLabel = 'Equipo entregado';
        } elseif ($policy === 'credit_allowed') {
            $deliveryLabel =
                'Habilitada por crédito autorizado';
        } elseif (
            $policy === 'payment_required'
            && $repairPaymentStatus === 'paid'
        ) {
            $deliveryLabel =
                'Habilitada · pago confirmado';
        } elseif ($policy === 'payment_required') {
            $deliveryLabel =
                'Bloqueada hasta recibir el pago';
        }

        if ($delivered) {
            $nextStep =
                'El equipo ya fue entregado. '
                . 'Continuar con el cierre cuando corresponda.';
        } elseif ($policy === 'credit_allowed') {
            $nextStep =
                'El crédito está autorizado. '
                . 'ATC puede continuar con la entrega al cliente.';
        } elseif (
            $policy === 'payment_required'
            && $repairPaymentStatus === 'paid'
        ) {
            $nextStep =
                'Pago confirmado. '
                . 'ATC puede continuar con Entregar al cliente.';
        } elseif ($receivableLooksFullyPaid) {
            $nextStep =
                'La CxC ya muestra cobro completo, '
                . 'pero ATC todavía espera la sincronización '
                . 'del estado de pago antes de habilitar la entrega.';
        } else {
            $nextStep =
                'Esperar el cobro por el área autorizada. '
                . 'ATC no registra el pago y permanece '
                . 'dentro de Reparaciones.';
        }

        $number = trim(
            (string) (
                $receivable->number
                ?? ''
            )
        );

        if ($number === '' && $receivableId > 0) {
            $number = 'CxC #' . $receivableId;
        }

        if ($number === '') {
            $number = 'Sin CxC';
        }

        return $cache[$recordId] = [
            'number' => $number,
            'payment_label' => $paymentLabel,
            'total' => $total,
            'collected' => $collected,
            'balance' => $balance,
            'policy_label' => $policyLabel,
            'delivery_label' => $deliveryLabel,
            'next_step' => $nextStep,
        ];
    }


    protected function viewAccountReceivableAction(): Action
    {
        return Action::make('view_account_receivable')
            ->label('Ver CxC')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('info')
            ->url(function (): string {
                $receivableId = (int) ($this->record->account_receivable_id ?? 0);

                if ($receivableId <= 0) {
                    return '#';
                }

                return AccountReceivableResource::getUrl('view', [
                    'record' => $receivableId,
                ]);
            })
            ->openUrlInNewTab(false)
            ->visible(
                fn (): bool =>
                    (int) (
                        $this->record->account_receivable_id
                        ?? 0
                    ) > 0
                    && AccountReceivableResource::canViewAny()
            );
    }

    protected function canShowEconomicClosureAction(): bool
    {
        $record = $this->record ?? null;

        if (! $record) {
            return false;
        }

        if ((int) ($record->account_receivable_id ?? 0) > 0) {
            return false;
        }

        $economicStatus = (string) ($record->economic_status ?? '');

        if (in_array($economicStatus, ['receivable_created', 'partially_charged', 'charged'], true)) {
            return false;
        }

        $paymentStatus = (string) ($record->economic_payment_status ?? '');

        if (in_array($paymentStatus, ['partial', 'paid'], true)) {
            return false;
        }

        return ServiceAccess::canManageRepairEconomic($record);
    }
}
