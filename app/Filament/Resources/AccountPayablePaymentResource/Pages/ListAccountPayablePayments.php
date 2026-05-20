<?php

namespace App\Filament\Resources\AccountPayablePaymentResource\Pages;

use App\Filament\Resources\AccountPayablePaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountPayablePayments extends ListRecords
{
    protected static string $resource = AccountPayablePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
