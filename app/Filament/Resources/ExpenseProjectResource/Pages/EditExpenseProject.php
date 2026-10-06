<?php

namespace App\Filament\Resources\ExpenseProjectResource\Pages;

use App\Filament\Resources\ExpenseProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpenseProject extends EditRecord
{
    protected static string $resource =
        ExpenseProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
