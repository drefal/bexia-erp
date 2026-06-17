<?php

namespace App\Filament\Resources\SatDownloadRequestResource\Pages;

use App\Filament\Resources\SatDownloadRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSatDownloadRequests extends ListRecords
{
    protected static string $resource = SatDownloadRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear solicitud SAT'),
        ];
    }
}
