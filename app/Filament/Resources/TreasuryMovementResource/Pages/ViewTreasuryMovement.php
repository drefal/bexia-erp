<?php

namespace App\Filament\Resources\TreasuryMovementResource\Pages;

use App\Filament\Resources\TreasuryMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTreasuryMovement extends ViewRecord
{
    protected static string $resource = TreasuryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /*
             * BEXIA_V5524B11_TREASURY_PRINT_IN_VIEW
             * Imprimir también debe estar disponible dentro del movimiento.
             */
            Actions\Action::make('print_movement')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('treasury.movements.print', ['movement' => $this->record]))
                ->openUrlInNewTab(),

            Actions\EditAction::make()
                ->label('Editar')
                ->visible(fn (): bool => $this->record->status === 'draft'),

            Actions\Action::make('post')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar movimiento')
                ->modalDescription('Al confirmar, este movimiento afectará el saldo de la cuenta/caja. Después ya no podrá editarse libremente.')
                ->visible(fn (): bool => auth()->user()?->can('treasury.update') && $this->record->status === 'draft')
                ->action(function (): void {
                    TreasuryMovementResource::postMovement($this->record);
                    $this->record->refresh();
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar movimiento')
                ->modalDescription('Si el movimiento ya estaba confirmado, se reversará su efecto en el saldo.')
                // BEXIA_V5524B4_TREASURY_CANCEL_MODAL_LABELS
                ->modalSubmitActionLabel('Sí, cancelar')
                ->modalCancelActionLabel('Salir')
                ->visible(fn (): bool => auth()->user()?->can('treasury.update') && in_array($this->record->status, ['draft', 'posted'], true))
                ->action(function (): void {
                    TreasuryMovementResource::cancelMovement($this->record);
                    $this->record->refresh();
                }),
        ];
    }
}
