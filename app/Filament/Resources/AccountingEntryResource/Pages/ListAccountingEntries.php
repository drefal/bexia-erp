<?php

namespace App\Filament\Resources\AccountingEntryResource\Pages;

use App\Filament\Resources\AccountingEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountingEntries extends ListRecords
{
    protected static string $resource = AccountingEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
