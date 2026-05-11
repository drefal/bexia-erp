<?php

namespace App\Filament\Resources\AccountingInventoryValuationLayerResource\Pages;

use App\Filament\Resources\AccountingInventoryValuationLayerResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountingInventoryValuationLayers extends ListRecords
{
    protected static string $resource = AccountingInventoryValuationLayerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
