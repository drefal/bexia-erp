<?php

namespace App\Filament\Resources\EmployeeAttendanceResource\Pages;

use App\Filament\Resources\EmployeeAttendanceResource;
use App\Models\Employee;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeAttendance extends CreateRecord
{
    protected static string $resource = EmployeeAttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $employee = Employee::query()->find($data['employee_id'] ?? null);

        $data['company_id'] = $employee?->company_id ?? Filament::getTenant()?->getKey() ?? $data['company_id'] ?? null;
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
