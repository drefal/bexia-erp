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
    protected static string $resource = RepairOrderResource::class;

    protected ?string $oldStatus = null;

    protected mixed $oldAssignedEmployeeId = null;

    protected mixed $uploadedAttachments = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->oldStatus = $this->record->status;
        $this->oldAssignedEmployeeId = $this->record->assigned_employee_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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

    protected function getHeaderActions(): array
    {
        return [
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



            \Filament\Actions\Action::make('mark_ready_for_delivery_fixed')
                ->label('Marcar listo para entrega')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Marcar como listo para entrega')
                ->modalDescription('La reparación quedará lista para que el cliente pueda recogerla.')
                ->visible(fn (): bool => (string) (\Illuminate\Support\Facades\DB::table('repair_orders')->where('id', $this->record->getKey())->value('workflow_stage') ?? '') === 'repaired')
                ->action(function (): void {
                    $this->markRepairReadyForDelivery();
                }),

            \Filament\Actions\Action::make('deliver_to_customer_fixed')
                ->label('Entregar al cliente')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->modalHeading('Entregar al cliente')
                ->modalSubmitActionLabel('Confirmar entrega')
                ->visible(fn (): bool => (string) (\Illuminate\Support\Facades\DB::table('repair_orders')->where('id', $this->record->getKey())->value('workflow_stage') ?? '') === 'ready_for_delivery')
                ->form([
                    \Filament\Forms\Components\TextInput::make('delivered_to')
                        ->label('Nombre de quien recibe')
                        ->required()
                        ->maxLength(255),

                    \Filament\Forms\Components\Textarea::make('delivery_notes')
                        ->label('Observaciones de entrega')
                        ->rows(4)
                        ->columnSpanFull(),

                    \Filament\Forms\Components\FileUpload::make('delivery_files')
                        ->label('Evidencia obligatoria de entrega')
                        ->required()
                        ->minFiles(1)
                        ->validationMessages([
                            'required' => 'Agrega al menos una foto o archivo de evidencia de entrega.',
                            'min' => 'Agrega al menos una foto o archivo de evidencia de entrega.',
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
                        ->helperText('Obligatorio: sube una foto del producto entregado, firma física escaneada, acuse o documento relacionado.')
                        ->disk('public')
                        ->directory('service/delivery-files')
                        ->multiple()
                        ->reorderable()
                        ->downloadable()
                        ->openable()
                        ->imagePreviewHeight('120')
                        ->maxFiles(10)
                        ->maxSize(10240)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->deliverRepairToCustomer($data);
                }),



            \Filament\Actions\Action::make('mark_ready_for_delivery')
                ->label('Marcar listo para entrega')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Marcar como listo para entrega')
                ->modalDescription('La reparación quedará lista para que el cliente pueda recogerla.')
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?? '') === 'repaired')
                ->action(function (): void {
                    $this->markRepairReadyForDelivery();
                }),

            \Filament\Actions\Action::make('deliver_to_customer')
                ->label('Entregar al cliente')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->modalHeading('Entregar al cliente')
                ->modalSubmitActionLabel('Confirmar entrega')
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?? '') === 'ready_for_delivery')
                ->form([
                    \Filament\Forms\Components\TextInput::make('delivered_to')
                        ->label('Nombre de quien recibe')
                        ->required()
                        ->maxLength(255),

                    \Filament\Forms\Components\Textarea::make('delivery_notes')
                        ->label('Observaciones de entrega')
                        ->rows(4)
                        ->columnSpanFull(),

                    \Filament\Forms\Components\FileUpload::make('delivery_files')
                        ->label('Evidencia obligatoria de entrega')
                        ->required()
                        ->minFiles(1)
                        ->validationMessages([
                            'required' => 'Agrega al menos una foto o archivo de evidencia de entrega.',
                            'min' => 'Agrega al menos una foto o archivo de evidencia de entrega.',
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
                        ->helperText('Obligatorio: sube una foto del producto entregado, firma física escaneada, acuse o documento relacionado.')
                        ->disk('public')
                        ->directory('service/delivery-files')
                        ->multiple()
                        ->reorderable()
                        ->downloadable()
                        ->openable()
                        ->imagePreviewHeight('120')
                        ->maxFiles(10)
                        ->maxSize(10240)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->deliverRepairToCustomer($data);
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

                    return \App\Support\Service\ServiceAccess::canSendRepairQuoteToApproval($record);
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

            \Filament\Actions\Action::make('stage_mark_quote_approved')
                ->label('Marcar aprobada')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'pending_approval')
                ->action(function (): void {
                    $record = $this->record;

                $this->saveSolutionFilesForRepair($record, (array) ($data['solution_files'] ?? []));


                    $record->update([
                        'workflow_stage' => 'quote_approved',
                        'status' => 'approved_pending_repair',
                        'quote_status' => 'customer_approved',
                        'quote_approved_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Cotizacion aprobada')
                        ->body('La reparacion queda pendiente de que el tecnico la tome.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            \Filament\Actions\Action::make('stage_start_repair')
                ->label('Tomar / iniciar reparacion')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'quote_approved')
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
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'in_repair')
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
                ->label('Enviar a revision supervisor')
                    ->hidden(fn (): bool => ! \App\Support\Service\ServiceAccess::canSendRepairToSupervisorReview($this->record))

                ->icon('heroicon-o-eye')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'repaired')
                ->action(function (): void {
                    $record = $this->record;

                    $record->update([
                        'workflow_stage' => 'supervisor_review',
                        'status' => 'supervisor_review',
                        'supervisor_review_requested_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Enviada a revision')
                        ->body('La reparacion queda pendiente de revision de supervisor.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            \Filament\Actions\Action::make('stage_approve_supervisor_review')
                ->label('Aprobar revision')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'supervisor_review')
                ->action(function (): void {
                    $record = $this->record;

                    $record->update([
                        'workflow_stage' => 'ready_for_delivery',
                        'status' => 'ready_for_delivery',
                        'supervisor_reviewed_at' => now(),
                        'ready_for_delivery_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Lista para entrega')
                        ->body('La reparacion ya puede entregarse al cliente.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            \Filament\Actions\Action::make('stage_deliver_repair')
                ->label('Entregar')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) ($this->record->workflow_stage ?: '') === 'ready_for_delivery')
                ->action(function (): void {
                    $record = $this->record;

                    $record->update([
                        'workflow_stage' => 'delivered',
                        'status' => 'delivered',
                        'delivered_at' => now(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Reparacion entregada')
                        ->body('La reparacion quedo cerrada.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $record]));
                }),

            \Filament\Actions\DeleteAction::make()
    ->visible(fn (): bool => $this->canDeleteRepairDraftAction()),
        ];
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

    protected function deliverRepairToCustomer(array $data): void
    {
        $record = $this->record;
        $now = now();

        $payload = $this->repairOrderPayloadForExistingColumns([
            'status' => 'delivered',
            'workflow_stage' => 'delivered',
            'delivered_at' => $now,
            'delivered_to' => $data['delivered_to'] ?? null,
            'delivery_notes' => $data['delivery_notes'] ?? null,
            'updated_at' => $now,
        ]);

        \Illuminate\Support\Facades\DB::table('repair_orders')
            ->where('id', $record->getKey())
            ->update($payload);

        $this->saveDeliveryFilesForRepair($record, (array) ($data['delivery_files'] ?? []));

        $this->createServiceRepairTransitionEvent(
            $record,
            'repair_delivered',
            'La reparación fue entregada al cliente.'
        );

        $record->refresh();

        \Filament\Notifications\Notification::make()
            ->title('Reparación entregada')
            ->success()
            ->send();
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
            ->visible(fn (): bool => $this->canShowEconomicClosureAction())
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
            ->visible(fn (): bool => (float) ($this->record->economic_total ?? $this->record->total_amount ?? 0) > 0);
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
            ->visible(fn (): bool => (int) ($this->record->account_receivable_id ?? 0) > 0);
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

        return true;
    }
}
