<?php

namespace App\Filament\Resources\SalesPriceListResource\Pages;

use App\Filament\Resources\SalesPriceListResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesPriceList extends CreateRecord
{
    protected static string $resource = SalesPriceListResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Filament::getTenant()) {
            $data['company_id'] = (int) Filament::getTenant()->getKey();
        } else {
            $data['company_id'] = (int) (request()->route('tenant') ?? auth()->user()?->company_id ?? 0);
        }

        return $data;
    }
}
