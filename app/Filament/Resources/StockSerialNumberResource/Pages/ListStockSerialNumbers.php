<?php

namespace App\Filament\Resources\StockSerialNumberResource\Pages;

use App\Filament\Resources\StockSerialNumberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockSerialNumbers extends ListRecords
{
    protected static string $resource = StockSerialNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo número de serie'),
        ];
    }
}
