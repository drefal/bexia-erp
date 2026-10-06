<?php

namespace App\Filament\Resources\ExpenseProjectResource\Pages;

use App\Filament\Resources\ExpenseProjectResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseProject extends CreateRecord
{
    protected static string $resource =
        ExpenseProjectResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $data['company_id'] =
            (int) Filament::getTenant()->getKey();

        return $data;
    }
}
