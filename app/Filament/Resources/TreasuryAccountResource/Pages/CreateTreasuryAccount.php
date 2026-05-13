<?php

namespace App\Filament\Resources\TreasuryAccountResource\Pages;

use App\Filament\Resources\TreasuryAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTreasuryAccount extends CreateRecord
{
    protected static string $resource = TreasuryAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['current_balance'] = $data['opening_balance'] ?? 0;

        return $data;
    }
}
