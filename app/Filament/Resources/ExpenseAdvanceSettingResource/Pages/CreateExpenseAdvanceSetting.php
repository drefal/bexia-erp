<?php

namespace App\Filament\Resources\ExpenseAdvanceSettingResource\Pages;

use App\Filament\Resources\ExpenseAdvanceSettingResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseAdvanceSetting extends CreateRecord
{
    protected static string $resource =
        ExpenseAdvanceSettingResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $data['company_id'] = (int) (
            Filament::getTenant()?->getKey() ?? 0
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl();
    }
}
