<?php

namespace App\Filament\Resources\TreasuryBankAccountResource\Pages;

use App\Filament\Resources\TreasuryBankAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTreasuryBankAccount extends CreateRecord
{
    protected static string $resource = TreasuryBankAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TreasuryBankAccountResource::sanitizeBankAccountData($data);
    }

    protected function afterCreate(): void
    {
        TreasuryBankAccountResource::setDefaultConcentrator($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
