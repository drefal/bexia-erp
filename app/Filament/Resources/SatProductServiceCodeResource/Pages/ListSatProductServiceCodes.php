<?php

namespace App\Filament\Resources\SatProductServiceCodeResource\Pages;

use App\Filament\Resources\SatProductServiceCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSatProductServiceCodes extends ListRecords
{
    protected static string $resource = SatProductServiceCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva clave SAT'),
        ];
    }
}
