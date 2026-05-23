<?php

namespace App\Filament\Resources\StockSerialSpecialMovementResource\Pages;

use App\Filament\Resources\StockSerialSpecialMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListStockSerialSpecialMovements extends ListRecords
{
    protected static string $resource = StockSerialSpecialMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
