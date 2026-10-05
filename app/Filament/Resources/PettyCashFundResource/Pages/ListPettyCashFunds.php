<?php

namespace App\Filament\Resources\PettyCashFundResource\Pages;

use App\Filament\Resources\PettyCashFundResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPettyCashFunds extends ListRecords
{
    protected static string $resource = PettyCashFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva caja chica'),
        ];
    }
}
