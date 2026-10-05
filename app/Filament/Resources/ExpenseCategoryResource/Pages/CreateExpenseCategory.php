<?php

namespace App\Filament\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Resources\ExpenseCategoryResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseCategory extends CreateRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
