<?php

namespace App\Filament\Resources\SatBillingCatalogItemResource\Pages;

use App\Filament\Resources\SatBillingCatalogItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSatBillingCatalogItems extends ListRecords
{
    protected static string $resource = SatBillingCatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo elemento'),
        ];
    }
}
