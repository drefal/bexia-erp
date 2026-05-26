<?php

namespace App\Filament\Resources\EmployeePayrollPerceptionResource\Pages;

use App\Filament\Resources\EmployeePayrollPerceptionResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePayrollPerception extends EditRecord
{
    protected static string $resource = EmployeePayrollPerceptionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
