<?php

namespace App\Filament\Resources\ExpenseProjectResource\Pages;

use App\Filament\Resources\ExpenseProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenseProjects extends ListRecords
{
    protected static string $resource =
        ExpenseProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo proyecto'),
        ];
    }
}
