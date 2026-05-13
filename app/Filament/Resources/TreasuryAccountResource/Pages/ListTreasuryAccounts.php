<?php

namespace App\Filament\Resources\TreasuryAccountResource\Pages;

use App\Filament\Resources\TreasuryAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreasuryAccounts extends ListRecords
{
    protected static string $resource = TreasuryAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
