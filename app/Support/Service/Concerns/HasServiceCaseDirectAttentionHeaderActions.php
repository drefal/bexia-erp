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
                ->modalSubmitActionLabel('Guardar respuesta')
                ->form([
                    Forms\Components\Textarea::make(
                        'response_notes'
                    )
                        ->label('Respuesta proporcionada')
                        ->rows(5)
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
                    )->registerResponse(
                        $this->record,
                        (string) (
                            $data['response_notes'] ?? ''
                        )
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Respuesta registrada')
                        ->body(
                            'La respuesta quedó registrada en la bitácora del ticket.'
                        )
                        ->success()
                        ->send();
                }),

            Action::make('direct_attention_wait_customer')
                ->label('Esperando cliente')
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

            Action::make('direct_attention_resolve')
                ->label('Resolver y cerrar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading(
                    'Resolver atención sin reparación'
                )
                ->modalDescription(
                    'Registra la solución final proporcionada al cliente.'
                )
                ->modalSubmitActionLabel(
                    'Resolver y cerrar'
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
                    )->resolveAndClose(
                        $this->record,
                        (string) (
                            $data['resolution_type'] ?? ''
                        ),
                        (string) (
                            $data['resolution_notes'] ?? ''
                        )
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Ticket resuelto')
                        ->body(
                            'La atención quedó cerrada sin generar reparación.'
                        )
                        ->success()
                        ->send();
                }),

            Action::make('direct_attention_convert_repair')
                ->label('Convertir a reparación')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('danger')
                ->modalHeading(
                    'Convertir atención a reparación'
                )
                ->modalDescription(
                    'El ticket se conserva y se creará una orden de reparación vinculada.'
                )
                ->modalSubmitActionLabel(
                    'Crear reparación'
                )
                ->form([
                    Forms\Components\Select::make(
                        'assigned_employee_id'
                    )
                        ->label('Técnico responsable')
                        ->options(
                            ServiceAccess::technicianEmployeeOptions()
                        )
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->default(
                            fn (): mixed =>
                                $this->record
                                    ->assigned_employee_id
                        )
                        ->required(),

                    Forms\Components\Textarea::make(
                        'initial_diagnosis'
                    )
                        ->label('Diagnóstico preliminar')
                        ->rows(4)
                        ->required(),

                    Forms\Components\DateTimePicker::make(
                        'promised_at'
                    )
                        ->label(
                            'Fecha compromiso de reparación'
                        )
                        ->default(
                            fn (): mixed =>
                                $this->record->due_at
                        )
                        ->required(),

                    Forms\Components\Select::make(
                        'warranty_status'
                    )
                        ->label('Garantía')
                        ->options(
                            RepairOrder::WARRANTY_STATUSES
                        )
                        ->default('no_aplica')
                        ->native(false)
                        ->required(),

                    Forms\Components\Toggle::make(
                        'requires_quote'
                    )
                        ->label(
                            'Requiere cotización al cliente'
                        )
                        ->default(true),

                    Forms\Components\Textarea::make(
                        'conversion_notes'
                    )
                        ->label(
                            'Motivo de conversión a reparación'
                        )
                        ->rows(4)
                        ->required(),
                ])
                ->visible(fn (): bool =>
                    $this->isDirectAttentionOpen()
                    && ServiceAccess::can(
                        'service.cases.classify'
                    )
                )
                ->action(function (array $data): void {
                    $repair = app(
                        ServiceCaseClassificationService::class
                    )->convertNonRepairToRepair(
                        $this->record,
                        $data
                    );

                    Notification::make()
                        ->title(
                            'Ticket convertido a reparación'
                        )
                        ->body(
                            'Se creó la orden '
                            . $repair->folio
                            . '.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        RepairOrderResource::getUrl(
                            'edit',
                            ['record' => $repair]
                        )
                    );
                }),

            Action::make('direct_attention_reopen')
                ->label('Reabrir atención')
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
