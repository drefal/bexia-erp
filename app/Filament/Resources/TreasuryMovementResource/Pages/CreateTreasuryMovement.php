<?php

namespace App\Filament\Resources\TreasuryMovementResource\Pages;

use App\Filament\Resources\TreasuryMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTreasuryMovement extends CreateRecord
{
    protected static string $resource = TreasuryMovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['posted_at'] = null;
        $data['cancelled_at'] = null;
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        /*
         * BEXIA_V5524B5_TREASURY_REDIRECT_CREATE_TO_VIEW
         * Después de crear, abrir Ver movimiento para mostrar Confirmar / Cancelar.
         */
        return static::getResource()::getUrl('view', [
            'record' => $this->record,
        ]);
    }
}
