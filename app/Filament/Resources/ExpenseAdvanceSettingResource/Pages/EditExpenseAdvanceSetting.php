<?php

namespace App\Filament\Resources\ExpenseAdvanceSettingResource\Pages;

use App\Filament\Resources\ExpenseAdvanceSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditExpenseAdvanceSetting extends EditRecord
{
    protected static string $resource =
        ExpenseAdvanceSettingResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl();
    }
}
