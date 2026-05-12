<?php

namespace App\Filament\Resources\BillingSeriesResource\Pages;

use App\Filament\Resources\BillingSeriesResource;
use Filament\Resources\Pages\ListRecords;

class ListBillingSeries extends ListRecords
{
    protected static string $resource = BillingSeriesResource::class;

    public function getTitle(): string
    {
        return 'Series de facturación';
    }
}
