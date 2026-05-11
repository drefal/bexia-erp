<?php

namespace App\Filament\Resources\CashDenominationResource\Pages;

use App\Filament\Resources\CashDenominationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashDenominations extends ListRecords
{
    protected static string $resource = CashDenominationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva denominación'),
        ];
    }
}
