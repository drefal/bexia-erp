<?php

namespace App\Filament\Resources\SalesPriceListResource\Pages;

use App\Filament\Resources\SalesPriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesPriceList extends EditRecord
{
    protected static string $resource = SalesPriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
