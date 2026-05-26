<?php

namespace App\Filament\Resources\EmployeeVacationBalanceResource\Pages;

use App\Filament\Resources\EmployeeVacationBalanceResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeVacationBalance extends EditRecord
{
    protected static string $resource = EmployeeVacationBalanceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
