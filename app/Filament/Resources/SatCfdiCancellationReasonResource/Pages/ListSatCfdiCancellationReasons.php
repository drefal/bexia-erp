<?php

namespace App\Filament\Resources\SatCfdiCancellationReasonResource\Pages;

use App\Filament\Resources\SatCfdiCancellationReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSatCfdiCancellationReasons extends ListRecords
{
    protected static string $resource = SatCfdiCancellationReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
