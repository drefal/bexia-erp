<?php

namespace App\Filament\Resources\TreasuryAccountResource\Pages;

use App\Filament\Resources\TreasuryAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditTreasuryAccount extends EditRecord
{
    protected static string $resource = TreasuryAccountResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TreasuryAccountResource::sanitizeAccountData($data);
    }

    protected function afterSave(): void
    {
        TreasuryAccountResource::setDefaultConcentrator($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
