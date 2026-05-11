<?php

namespace App\Filament\Resources\CashDenominationResource\Pages;

use App\Filament\Resources\CashDenominationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCashDenomination extends EditRecord
{
    protected static string $resource = CashDenominationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
