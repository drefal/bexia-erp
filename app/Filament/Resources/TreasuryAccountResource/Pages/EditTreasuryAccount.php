<?php

namespace App\Filament\Resources\TreasuryAccountResource\Pages;

use App\Filament\Resources\TreasuryAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTreasuryAccount extends EditRecord
{
    protected static string $resource = TreasuryAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
