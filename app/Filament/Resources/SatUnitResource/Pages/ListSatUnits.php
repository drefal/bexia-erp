<?php

namespace App\Filament\Resources\SatUnitResource\Pages;

use App\Filament\Resources\SatUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSatUnits extends ListRecords
{
    protected static string $resource = SatUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva unidad SAT'),
        ];
    }
}
