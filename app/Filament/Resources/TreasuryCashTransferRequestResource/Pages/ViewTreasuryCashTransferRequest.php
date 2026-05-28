<?php

namespace App\Filament\Resources\TreasuryCashTransferRequestResource\Pages;

use App\Filament\Resources\TreasuryCashTransferRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTreasuryCashTransferRequest extends ViewRecord
{
    protected static string $resource = TreasuryCashTransferRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => TreasuryCashTransferRequestResource::canEdit($this->record)),
        ];
    }
}
