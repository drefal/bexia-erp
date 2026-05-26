<?php

namespace App\Filament\Resources\EmployeeAttendanceResource\Pages;

use App\Filament\Resources\EmployeeAttendanceResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeAttendance extends EditRecord
{
    protected static string $resource = EmployeeAttendanceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
