<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Models\StockMovement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('inventory.stock-movements.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('confirm')
                ->label('Confirmar traslado')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar traslado')
                ->modalDescription('Al confirmar, se actualizarán las existencias y el traslado quedará bloqueado.')
                ->modalSubmitActionLabel('Confirmar traslado')
                ->visible(fn (): bool => $this->record instanceof StockMovement && $this->record->status === 'draft')
                ->action(function (): void {
                    StockMovementResource::confirmMovement($this->record);

                    Notification::make()
                        ->title('Traslado confirmado')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->label('Eliminar borrador')
                ->visible(fn (): bool => $this->record instanceof StockMovement && $this->record->status === 'draft'),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->record instanceof StockMovement && in_array($this->record->status, ['done', 'cancelled'], true)) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('Traslado guardado')
            ->success()
            ->send();
    }
}
