<?php

namespace App\Filament\Resources\TreasuryMovementResource\Pages;

use App\Filament\Resources\TreasuryMovementResource;
use Filament\Resources\Pages\EditRecord;

class EditTreasuryMovement extends EditRecord
{
    protected static string $resource = TreasuryMovementResource::class;

    protected function getHeaderActions(): array
    {
        /*
         * BEXIA_V5524B2_TREASURY_NO_DELETE_HEADER
         * No se permite borrar movimientos de tesorería.
         */
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['status'], $data['posted_at'], $data['cancelled_at']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        /*
         * BEXIA_V5524B5_TREASURY_REDIRECT_EDIT_TO_VIEW
         * Después de guardar edición, abrir Ver movimiento para mostrar Confirmar / Cancelar.
         */
        return static::getResource()::getUrl('view', [
            'record' => $this->record,
        ]);
    }
}
