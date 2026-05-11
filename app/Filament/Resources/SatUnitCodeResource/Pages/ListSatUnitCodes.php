<?php

namespace App\Filament\Resources\SatUnitCodeResource\Pages;

use App\Filament\Resources\SatUnitCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSatUnitCodes extends ListRecords
{
    protected static string $resource = SatUnitCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva unidad SAT'),
        ];
    }
}
