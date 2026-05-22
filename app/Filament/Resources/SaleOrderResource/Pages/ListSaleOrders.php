<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListSaleOrders extends ListRecords
{
    protected static string $resource = SaleOrderResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->redirect(SaleOrderResource::getUrl('quotes'));
    }
}
