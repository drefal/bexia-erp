<?php

namespace App\Filament\Resources\ExpenseAdvanceSettingResource\Pages;

use App\Filament\Resources\ExpenseAdvanceSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenseAdvanceSettings extends ListRecords
{
    protected static string $resource =
        ExpenseAdvanceSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear configuración')
                ->visible(
                    fn (): bool =>
                        ExpenseAdvanceSettingResource::canCreate()
                ),
        ];
    }
}
