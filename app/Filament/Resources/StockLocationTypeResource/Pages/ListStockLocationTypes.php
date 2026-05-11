<?php

namespace App\Filament\Resources\StockLocationTypeResource\Pages;

use App\Filament\Resources\StockLocationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockLocationTypes extends ListRecords
{
    protected static string $resource = StockLocationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo tipo'),
        ];
    }
}
