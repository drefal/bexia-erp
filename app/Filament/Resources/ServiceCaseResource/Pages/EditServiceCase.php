<?php

namespace App\Filament\Resources\ServiceCaseResource\Pages;

use App\Filament\Resources\RepairOrderResource;
use App\Filament\Resources\ServiceCaseResource;
use App\Models\RepairOrder;
use App\Models\ServiceCase;
use App\Support\Service\ServiceAccess;
use App\Support\Service\ServiceCaseClassificationService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditServiceCase extends EditRecord
{
    use \App\Support\Service\Concerns\HasServiceCaseDirectAttentionHeaderActions;

    protected static string $resource = ServiceCaseResource::class;

    protected ?string $oldStatus = null;

    protected mixed $oldAssignedEmployeeId = null;

    protected mixed $uploadedAttachments = [];

    protected function getHeaderActions(): array
    {
        return [
            ...$this->serviceCaseDirectAttentionHeaderActions(),

            Action::make('classify_attention')
                ->label('Clasificar atención')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->modalHeading('Clasificar atención')
                ->modalDescription(
                    'Define si el ticket genera una reparación o continúa como servicio sin reparación.'
                )
                ->modalSubmitActionLabel('Confirmar clasificación')
                ->form([
                    Forms\Components\Radio::make('attention_route')
                        ->label('Ruta de atención')
                        ->options(ServiceCase::ATTENTION_ROUTES)
                        ->descriptions([
                            'repair' => 'Crea una orden de reparación vinculada al ticket.',
                            'non_repair' => 'La atención continúa y se resuelve dentro del ticket.',
                        ])
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('assigned_employee_id')
                        ->label('Responsable')
                        ->helperText('Para Reparación es obligatorio seleccionar técnico.')
                        ->options(ServiceAccess::technicianEmployeeOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->default(fn (): mixed => $this->record->assigned_employee_id)
                        ->visible(fn (Get $get): bool => filled($get('attention_route')))
                        ->required(fn (Get $get): bool => $get('attention_route') === 'repair'),

                    Forms\Components\Textarea::make('initial_diagnosis')
                        ->label('Diagnóstico preliminar')
                        ->rows(4)
                        ->required(fn (Get $get): bool => $get('attention_route') === 'repair')
                        ->visible(fn (Get $get): bool => $get('attention_route') === 'repair'),

                    Forms\Components\DateTimePicker::make('promised_at')
                        ->label('Fecha compromiso de reparación')
                        ->default(fn (): mixed => $this->record->due_at)
                        ->required(fn (Get $get): bool => $get('attention_route') === 'repair')
                        ->visible(fn (Get $get): bool => $get('attention_route') === 'repair'),

                    Forms\Components\Select::make('warranty_status')
                        ->label('Garantía')
                        ->options(RepairOrder::WARRANTY_STATUSES)
                        ->default('no_aplica')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('attention_route') === 'repair')
                        ->visible(fn (Get $get): bool => $get('attention_route') === 'repair'),

                    Forms\Components\Toggle::make('requires_quote')
                        ->label('Requiere cotización al cliente')
                        ->default(true)
                        ->visible(fn (Get $get): bool => $get('attention_route') === 'repair'),

                    Forms\Components\Select::make('non_repair_type')
                        ->label('Tipo de atención sin reparación')
                        ->options(ServiceCase::NON_REPAIR_TYPES)
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('attention_route') === 'non_repair')
                        ->visible(fn (Get $get): bool => $get('attention_route') === 'non_repair'),

                    Forms\Components\DateTimePicker::make('non_repair_due_at')
                        ->label('Fecha compromiso')
                        ->default(fn (): mixed => $this->record->due_at)
                        ->visible(fn (Get $get): bool => $get('attention_route') === 'non_repair'),

                    Forms\Components\Textarea::make('classification_notes')
                        ->label('Notas de clasificación')
                        ->helperText('Explica brevemente por qué se eligió esta ruta.')
                        ->rows(4)
                        ->required(),
                ])
                ->visible(fn (): bool =>
                    ServiceAccess::can('service.cases.classify')
                    && blank($this->record->attention_route)
                    && ! in_array(
                        (string) $this->record->status,
                        ['entregado', 'cerrado', 'rechazado', 'cancelado'],
                        true
                    )
                    && ! $this->record->repairOrders()->exists()
                )
                ->action(function (array $data): void {
                    $repair = app(
                        ServiceCaseClassificationService::class
                    )->classify($this->record, $data);

                    $this->record->refresh();

                    if ($repair) {
                        Notification::make()
                            ->title('Ticket clasificado como reparación')
                            ->body(
                                'Se creó la orden ' . $repair->folio . '.'
                            )
                            ->success()
                            ->send();

                        $this->redirect(
                            RepairOrderResource::getUrl(
                                'edit',
                                ['record' => $repair]
                            )
                        );

                        return;
                    }

                    Notification::make()
                        ->title('Ticket clasificado sin reparación')
                        ->body(
                            'La atención continuará dentro del ticket.'
                        )
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

        if (empty($data['product_name']) && ! empty($data['product_id'])) {
            $data['product_name'] = ServiceAccess::productLabel((int) $data['product_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        ServiceAccess::saveUploadedAttachments(
            companyId: $this->record->company_id,
            serviceCaseId: $this->record->id,
            repairOrderId: null,
            files: $this->uploadedAttachments,
            stage: 'ticket'
        );

        if ($this->oldStatus !== $this->record->status) {
            ServiceCaseResource::logEvent(
                $this->record,
                'cambio_estado_ticket',
                $this->oldStatus,
                $this->record->status,
                'Cambio de estado desde Filament.'
            );

            return;
        }

        if ((string) ($this->oldAssignedEmployeeId ?? '') !== (string) ($this->record->assigned_employee_id ?? '')) {
            ServiceCaseResource::logEvent(
                $this->record,
                'reasignacion_ticket',
                $this->record->status,
                $this->record->status,
                'Cambio de responsable desde Filament.'
            );

            return;
        }

        ServiceCaseResource::logEvent(
            $this->record,
            'ticket_actualizado',
            $this->record->status,
            $this->record->status,
            'Ticket actualizado desde Filament.'
        );
    }
}
