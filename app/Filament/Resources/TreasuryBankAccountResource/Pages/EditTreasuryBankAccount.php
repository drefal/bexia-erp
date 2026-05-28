<?php

namespace App\Filament\Resources\TreasuryBankAccountResource\Pages;

use App\Filament\Resources\TreasuryBankAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditTreasuryBankAccount extends EditRecord
{
    protected static string $resource = TreasuryBankAccountResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TreasuryBankAccountResource::sanitizeBankAccountData($data);
    }

    protected function afterSave(): void
    {
        TreasuryBankAccountResource::setDefaultConcentrator($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
