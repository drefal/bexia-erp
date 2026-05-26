<?php

namespace App\Filament\Resources\EmployeePayrollDeductionResource\Pages;

use App\Filament\Resources\EmployeePayrollDeductionResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePayrollDeduction extends EditRecord
{
    protected static string $resource = EmployeePayrollDeductionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
