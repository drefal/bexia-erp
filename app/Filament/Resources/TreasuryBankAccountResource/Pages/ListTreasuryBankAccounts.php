<?php

namespace App\Filament\Resources\TreasuryBankAccountResource\Pages;

use App\Filament\Resources\TreasuryBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreasuryBankAccounts extends ListRecords
{
    protected static string $resource = TreasuryBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva cuenta bancaria'),
        ];
    }
}
