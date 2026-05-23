<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('inventory.stock-adjustments.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('auditLog')
                ->label('Auditoría')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->modalHeading(fn (): string => 'Auditoría del ajuste ' . ($this->record?->reference ?: ('#' . $this->record?->id)))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('5xl')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => view('filament.inventory.stock-adjustments.audit-log', [
                    'record' => $this->record,
                    'audit' => StockAdjustmentResource::auditLogRows($this->record),
                ])),

            Actions\Action::make('confirm')
                ->label('Confirmar ajuste')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar ajuste de inventario')
                ->modalDescription('Al confirmar, se actualizarán las existencias y el ajuste quedará bloqueado. Ya no podrá editarse ni eliminarse.')
                ->modalSubmitActionLabel('Confirmar ajuste')
                ->visible(fn (): bool => $this->record instanceof StockAdjustment && $this->record->status === 'draft')
                ->action(function (): void {
                    StockAdjustmentResource::confirmAdjustment($this->record);

                    Notification::make()
                        ->title('Ajuste confirmado')
                        ->body('Las existencias fueron actualizadas y el ajuste quedó bloqueado.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('cancelAdjustment')
                ->label('Cancelar ajuste')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar ajuste')
                ->modalDescription('El ajuste quedará cancelado y bloqueado. No afectará existencias. Esta acción solo aplica a ajustes en borrador.')
                ->modalSubmitActionLabel('Sí, cancelar ajuste')
                ->form([
                    \Filament\Forms\Components\Textarea::make('cancellation_reason')
                        ->label('Motivo de cancelación')
                        ->required()
                        ->rows(4)
                        ->helperText('Explica por qué se cancela este ajuste. Este motivo quedará en la auditoría.'),
                ])
                ->visible(fn (): bool => $this->record && $this->record->status === 'draft')
                ->action(function (array $data): void {
                    $previousStatus = (string) $this->record->status;
                    $reason = trim((string) ($data['cancellation_reason'] ?? ''));

                    if ($reason === '') {
                        \Filament\Notifications\Notification::make()
                            ->title('Motivo requerido')
                            ->body('Captura el motivo de cancelación.')
                            ->danger()
                            ->send();

                        throw new \Filament\Support\Exceptions\Halt();
                    }

                    $this->record->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => $reason,
                        'cancelled_by' => auth()->id(),
                        'cancelled_at' => now(),
                    ]);

                    StockAdjustmentResource::recordAdjustmentStatusLog(
                        $this->record,
                        $previousStatus,
                        'cancelled',
                        'cancel',
                        $reason,
                        ['cancelled_by' => auth()->id()]
                    );

                    Notification::make()
                        ->title('Ajuste cancelado')
                        ->body('El ajuste quedó cancelado y no afectará existencias.')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->label('Eliminar borrador')
                ->visible(fn (): bool => $this->record instanceof StockAdjustment && $this->record->status === 'draft'),

        ];
    }

    protected function getFormActions(): array
    {
        if ($this->record instanceof StockAdjustment && in_array($this->record->status, ['done', 'cancelled'], true)) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reason' => 'Captura el motivo del ajuste antes de guardar cambios.',
            ]);
        }

        $data['reason'] = $reason;

        if ($this->record instanceof StockAdjustment && in_array($this->record->status, ['done', 'cancelled'], true)) {
            Notification::make()
                ->title('Este ajuste ya está bloqueado')
                ->body('Los ajustes confirmados o cancelados no se pueden modificar.')
                ->warning()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        StockAdjustmentResource::assertAdjustmentLinesCanBeSaved(
            isset($data['location_id']) ? (int) $data['location_id'] : null,
            $data['lines'] ?? []
        );

        return $data;
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('Ajuste guardado')
            ->success()
            ->send();
    }
}
