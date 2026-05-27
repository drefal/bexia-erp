<?php

namespace App\Filament\Resources\TreasuryCashTransferRequestResource\Pages;

use App\Filament\Resources\TreasuryCashTransferRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreasuryCashTransferRequests extends ListRecords
{
    protected static string $resource = TreasuryCashTransferRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva solicitud'),
        ];
    }
}
