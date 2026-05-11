<?php

namespace App\Filament\Resources\SalesPriceListResource\Pages;

use App\Filament\Resources\SalesPriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesPriceLists extends ListRecords
{
    protected static string $resource = SalesPriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva lista'),
        ];
    }
}
