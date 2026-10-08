<?php

namespace App\Filament\Resources\ExpenseAdvanceResource\Pages;

use App\Filament\Resources\ExpenseAdvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenseAdvances extends ListRecords
{
    protected static string $resource =
        ExpenseAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo anticipo'),
        ];
    }
}
