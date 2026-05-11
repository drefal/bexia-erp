<?php

namespace App\Filament\Resources\AccountingPostingAuditResource\Pages;

use App\Filament\Resources\AccountingPostingAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountingPostingAudits extends ListRecords
{
    protected static string $resource = AccountingPostingAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
