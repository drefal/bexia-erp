<?php

namespace App\Filament\Resources\TreasuryAccountResource\Pages;

use App\Filament\Resources\TreasuryAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTreasuryAccount extends CreateRecord
{
    protected static string $resource = TreasuryAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TreasuryAccountResource::sanitizeAccountData($data);
    }

    protected function afterCreate(): void
    {
        TreasuryAccountResource::setDefaultConcentrator($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
