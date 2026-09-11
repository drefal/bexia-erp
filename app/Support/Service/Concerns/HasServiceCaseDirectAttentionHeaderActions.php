<?php

namespace App\Support\Service\Concerns;

use App\Filament\Resources\RepairOrderResource;
use App\Filament\Resources\ServiceCaseResource;
use App\Models\RepairOrder;
use App\Support\Service\ServiceAccess;
use App\Support\Service\ServiceCaseClassificationService;
use App\Support\Service\ServiceCaseDirectAttentionService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

trait HasServiceCaseDirectAttentionHeaderActions
{
    protected function serviceCaseDirectAttentionHeaderActions(): array
    {
        return [
            Action::make('direct_attention_response')
                ->label('Registrar respuesta')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->modalHeading('Registrar respuesta al cliente')
                ->modalSubmitActionLabel('Registrar y resolver')
                ->form([
                    Forms\Components\Textarea::make(
                        'response_notes'
                    )
                        ->label('Respuesta proporcionada')
                        ->rows(5)
                        ->required(),

                    Forms\Components\FileUpload::make(
                        'response_files'
                    )
                        ->label(
                            'Imágenes / evidencias de resolución'
                        )
                        ->helperText(
                            'Puedes adjuntar una o varias fotografías o documentos que respalden la resolución.'
                        )
                        ->multiple()
                        ->acceptedFileTypes([
                            'image/*',
                            'application/pdf',
                        ])
                        ->disk('public')
                        ->directory(
                            'service-attachments/direct-attention-responses'
                        )
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->imagePreviewHeight('160')
                        ->maxSize(20480)
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool =>
                    $this->isDirectAttentionOpen()
                    && (string) ($this->record->status ?? '') !== 'resuelto'
                    && ServiceAccess::canRespondToDirectServiceCase(
                        $this->record
                    )
                )
                ->action(function (array $data): void {
                    app(
                        ServiceCaseDirectAttentionService::class
                    )->registerResponse(
                        $this->record,
                        (string) (
                            $data['response_notes'] ?? ''
                        ),
                        $data['response_files'] ?? null
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Ticket resuelto')
                        ->body(
                            'La respuesta quedó registrada y el ticket quedó marcado como Resuelto.'
                        )
                        ->success()
                        ->send();
                }),

            Action::make('direct_attention_wait_customer')
                ->label('Esperando cliente')
                ->hidden(
                    fn (): bool =>
                        ServiceAccess::isRestrictedServiceTechnician()
                )
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->modalHeading(
                    'Marcar como esperando cliente'
                )
                ->modalSubmitActionLabel(
                    'Esperar respuesta'
                )
                ->form([
                    Forms\Components\Textarea::make(
                        'waiting_notes'
                    )
                        ->label(
                            'Información solicitada al cliente'
                        )
                        ->rows(4)
                        ->required(),
                ])
                ->visible(fn (): bool =>
                    $this->isDirectAttentionOpen()
                    && ServiceAccess::can(
                        'service.cases.update'
                    )
                )
                ->action(function (array $data): void {
                    app(
                        ServiceCaseDirectAttentionService::class
                    )->waitForCustomer(
                        $this->record,
                        (string) (
                            $data['waiting_notes'] ?? ''
                        )
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Ticket esperando cliente')
                        ->success()
                        ->send();
                }),

            /*
             * V5.83.4b:
             * La validación obligatoria de la respuesta se retira
             * del flujo normal. Los eventos históricos se conservan.
             */

            // BEXIA_ATC_DIRECT_SOLUTION_PRINT_ACTION_V5_82_P7H32D
            Action::make('direct_attention_print_solution')
                ->label('Imprimir solución')
                ->hidden(
                    fn (): bool =>
                        ServiceAccess::isRestrictedServiceTechnician()
                )
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (): bool =>
                    (string) ($this->record->attention_route ?? '') === 'non_repair'
                    && in_array(
                        (string) ($this->record->status ?? ''),
                        ['resuelto', 'cerrado'],
                        true
                    )
                )
                ->url(fn (): string => route(
                    'service.service-cases.solution.print',
                    [
                        'tenant' => ServiceAccess::currentCompanyId()
                            ?? (int) $this->record->company_id,
                        'record' => $this->record->id,
                    ]
                ))
                ->openUrlInNewTab(),

            Action::make('direct_attention_resolve')
                ->label('Resolver ticket')
                ->hidden()
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading(
                    'Resolver atención / gestión'
                )
                ->modalDescription(
                    'Registra la solución. El ticket quedará Resuelto y podrá cerrarse después.'
                )
                ->modalSubmitActionLabel(
                    'Resolver ticket'
                )
                ->form([
                    Forms\Components\Select::make(
                        'resolution_type'
                    )
                        ->label('Tipo de resolución')
                        ->options(
                            ServiceCaseDirectAttentionService::RESOLUTION_TYPES
                        )
                        ->native(false)
                        ->required(),

                    Forms\Components\Textarea::make(
                        'resolution_notes'
                    )
                        ->label('Solución proporcionada')
                        ->rows(5)
                        ->required(),

                    Forms\Components\FileUpload::make(
                        'resolution_files'
                    )
                        ->label(
                            'Imágenes / evidencias de resolución'
                        )
                        ->multiple()
                        ->acceptedFileTypes([
                            'image/*',
                            'application/pdf',
                        ])
                        ->disk('public')
                        ->directory(
                            'service-attachments/resolution'
                        )
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->maxSize(20480)
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool =>
                    $this->isDirectAttentionOpen()
                    && ServiceAccess::canRespondToDirectServiceCase(
                        $this->record
                    )
                )
                ->action(function (array $data): void {
                    app(
                        ServiceCaseDirectAttentionService::class
                    )->resolve(
                        $this->record,
                        (string) (
                            $data['resolution_type'] ?? ''
                        ),
                        (string) (
                            $data['resolution_notes'] ?? ''
                        ),
                        $data['resolution_files'] ?? null
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Ticket resuelto')
                        ->body(
                            'La atención quedó Resuelta. El cierre se realiza como paso separado.'
                        )
                        ->success()
                        ->send();
                }),

            Action::make('direct_attention_close')
                ->label('Cerrar ticket')
                ->hidden(
                    fn (): bool =>
                        ServiceAccess::isRestrictedServiceTechnician()
                )
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->modalHeading('Cerrar ticket')
                ->modalDescription(
                    'Confirma el cierre final del expediente.'
                )
                ->modalSubmitActionLabel('Cerrar ticket')
                ->form([
                    Forms\Components\Select::make(
                        'customer_conformity'
                    )
                        ->label('¿Cliente conforme?')
                        ->options([
                            'yes' => 'Sí',
                            'no' => 'No',
                            'not_asked' => 'No se preguntó',
                        ])
                        ->default('not_asked')
                        ->native(false)
                        ->required(),

                    Forms\Components\Textarea::make(
                        'final_comment'
                    )
                        ->label('Nota de cierre')
                        ->rows(4),

                    Forms\Components\FileUpload::make(
                        'closure_files'
                    )
                        ->label(
                            'Imágenes / evidencias de cierre'
                        )
                        ->multiple()
                        ->acceptedFileTypes([
                            'image/*',
                            'application/pdf',
                        ])
                        ->disk('public')
                        ->directory(
                            'service-attachments/closure'
                        )
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->maxSize(20480)
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool =>
                    (string) (
                        $this->record->attention_route
                        ?? ''
                    ) === 'non_repair'
                    && (string) (
                        $this->record->status
                        ?? ''
                    ) === 'resuelto'
                    && ServiceAccess::can(
                        'service.cases.update'
                    )
                )
                ->action(function (array $data): void {
                    app(
                        ServiceCaseDirectAttentionService::class
                    )->close(
                        $this->record,
                        (string) (
                            $data['final_comment'] ?? ''
                        ),
                        isset($data['customer_conformity'])
                            ? (string) $data['customer_conformity']
                            : null,
                        $data['closure_files'] ?? null
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Ticket cerrado')
                        ->body(
                            'El expediente quedó cerrado.'
                        )
                        ->success()
                        ->send();
                }),

            /*
             * BEXIA_ATC_CONVERT_REPAIR_FULL_ROUTE_V5_83_4C5G3
             *
             * Convertir una atención a reparación ahora
             * prepara la recepción física.
             *
             * NO crea RepairOrder en este paso.
             */
            Action::make(
                'direct_attention_convert_repair'
            )
                ->label(
                    'Convertir a reparación'
                )
                ->hidden(
                    fn (): bool =>
                        ServiceAccess::
                            isRestrictedServiceTechnician()
                )
                ->icon(
                    'heroicon-o-wrench-screwdriver'
                )
                ->color('danger')
                ->modalHeading(
                    'Convertir atención a reparación'
                )
                ->modalDescription(
                    'El ticket se conservará y entrará al flujo normal de recepción. La orden técnica se creará únicamente cuando el equipo sea recibido físicamente.'
                )
                ->modalSubmitActionLabel(
                    'Preparar recepción'
                )
                ->form([
                    Forms\Components\Select::make(
                        'assigned_employee_id'
                    )
                        /*
                         * BEXIA_ATC_CONVERT_REPAIR_VALID_TECH_DEFAULT_V5_83_4C5G7F
                         *
                         * El responsable de una Gestion puede ser
                         * Carolina, Nestor u otro responsable ATC.
                         *
                         * Al pasar a Reparacion solo conservamos el
                         * responsable si realmente pertenece a la
                         * lista tecnica. De lo contrario el campo
                         * inicia vacio y obliga a elegir tecnico.
                         */
                        ->label(
                            'Técnico responsable'
                        )
                        ->options(
                            ServiceAccess::
                                technicianEmployeeOptions()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->default(
                            function (): ?int {
                                $currentEmployeeId =
                                    (int) (
                                        $this->record
                                            ->assigned_employee_id
                                        ?? 0
                                    );

                                if (
                                    $currentEmployeeId <= 0
                                ) {
                                    return null;
                                }

                                $technicians =
                                    ServiceAccess::
                                        technicianEmployeeOptions();

                                return array_key_exists(
                                    $currentEmployeeId,
                                    $technicians
                                )
                                    ? $currentEmployeeId
                                    : null;
                            }
                        )
                        ->helperText(
                            'Selecciona quién será responsable técnico de la reparación. Si el responsable actual sólo atiende Gestión, no se conserva automáticamente.'
                        )
                        ->required(),

                    Forms\Components\Select::make(
                        'repair_arrival_method'
                    )
                        ->label(
                            '¿Cómo llegará el equipo?'
                        )
                        ->options(
                            \App\Models\ServiceCase::
                                REPAIR_ARRIVAL_METHODS
                        )
                        ->native(false)
                        ->required(),

                    Forms\Components\TextInput::make(
                        'repair_reception_place'
                    )
                        ->label(
                            'Lugar de recepción'
                        )
                        ->placeholder(
                            'Ej. CEDIS, Calle 2, domicilio del cliente'
                        )
                        ->required()
                        ->maxLength(255),

                    Forms\Components\DateTimePicker::make(
                        'planned_reception_at'
                    )
                        ->label(
                            'Fecha prevista de recepción'
                        )
                        ->helperText(
                            'Opcional. No es todavía la fecha compromiso de reparación.'
                        ),

                    Forms\Components\Textarea::make(
                        'conversion_notes'
                    )
                        ->label(
                            'Motivo de conversión a reparación'
                        )
                        ->rows(4)
                        ->required(),

                    \Filament\Forms\Components\FileUpload::
                        make(
                            'conversion_files'
                        )
                        ->label(
                            'Evidencia previa a recepción'
                        )
                        ->helperText(
                            'Opcional. Fotos o PDF relacionados con la conversión.'
                        )
                        ->disk('public')
                        ->directory(
                            'service/pre-reception'
                        )
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'application/pdf',
                        ])
                        ->maxSize(10240)
                        ->multiple(),
                ])
                ->visible(
                    fn (): bool =>
                        $this->isDirectAttentionOpen()
                        && ServiceAccess::can(
                            'service.cases.classify'
                        )
                )
                ->action(
                    function (array $data): void {
                        app(
                            ServiceCaseClassificationService::
                                class
                        )->convertNonRepairToRepair(
                            $this->record,
                            $data
                        );

                        /*
                         * Las evidencias siguen perteneciendo
                         * a la etapa pre_reception porque
                         * todavía NO existe RepairOrder.
                         */
                        ServiceAccess::
                            saveUploadedAttachments(
                                companyId:
                                    $this->record
                                        ->company_id,

                                serviceCaseId:
                                    $this->record
                                        ->id,

                                repairOrderId:
                                    null,

                                files:
                                    $data[
                                        'conversion_files'
                                    ]
                                    ?? null,

                                stage:
                                    'pre_reception',

                                isCustomerVisible:
                                    false
                            );

                        $this->record->refresh();

                        Notification::make()
                            ->title(
                                'Recepción preparada'
                            )
                            ->body(
                                'El ticket fue convertido a reparación y quedó en espera del equipo. La orden técnica se creará al registrar la recepción física.'
                            )
                            ->success()
                            ->send();

                        $this->redirect(
                            ServiceCaseResource::
                                getUrl(
                                    'edit',
                                    [
                                        'record' =>
                                            $this->record,
                                    ]
                                )
                        );
                    }
                ),

            Action::make('direct_attention_reopen')
                ->label('Reabrir atención')
                ->hidden(
                    fn (): bool =>
                        ServiceAccess::isRestrictedServiceTechnician()
                )
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->modalHeading(
                    'Reabrir ticket'
                )
                ->modalSubmitActionLabel(
                    'Reabrir'
                )
                ->form([
                    Forms\Components\Textarea::make(
                        'reopen_reason'
                    )
                        ->label(
                            'Motivo de reapertura'
                        )
                        ->rows(4)
                        ->required(),
                ])
                ->visible(fn (): bool =>
                    (string) (
                        $this->record->attention_route
                        ?? ''
                    ) === 'non_repair'
                    && (string) (
                        $this->record->status
                        ?? ''
                    ) === 'cerrado'
                    && ServiceAccess::can(
                        'service.cases.update'
                    )
                )
                ->action(function (array $data): void {
                    app(
                        ServiceCaseDirectAttentionService::class
                    )->reopen(
                        $this->record,
                        (string) (
                            $data['reopen_reason'] ?? ''
                        )
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Ticket reabierto')
                        ->success()
                        ->send();

                    $this->redirect(
                        ServiceCaseResource::getUrl(
                            'edit',
                            ['record' => $this->record]
                        )
                    );
                }),
        ];
    }

    protected function latestDirectAttentionResponseValidated(): bool
    {
        if (
            ! isset($this->record)
            || ! $this->record->exists
        ) {
            return false;
        }

        return app(
            ServiceCaseDirectAttentionService::class
        )->latestResponseIsValidated(
            $this->record
        );
    }

    protected function hasDirectAttentionResponse(): bool
    {
        if (
            ! isset($this->record)
            || ! $this->record->exists
            || ! ServiceAccess::tableExists(
                'service_case_events'
            )
        ) {
            return false;
        }

        return $this->record
            ->events()
            ->where(
                'event_type',
                ServiceCaseDirectAttentionService::EVENT_RESPONSE
            )
            ->exists();
    }

    protected function isDirectAttentionOpen(): bool
    {
        if (
            (string) (
                $this->record->attention_route ?? ''
            ) !== 'non_repair'
        ) {
            return false;
        }

        if (
            in_array(
                (string) (
                    $this->record->status ?? ''
                ),
                [
                    'cerrado',
                    'rechazado',
                    'cancelado',
                    'entregado',
                    'resuelto',
                ],
                true
            )
        ) {
            return false;
        }

        return ! $this->record
            ->repairOrders()
            ->withTrashed()
            ->exists();
    }
}
