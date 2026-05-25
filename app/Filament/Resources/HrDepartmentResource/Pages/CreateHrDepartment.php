<?php

namespace App\Filament\Resources\HrDepartmentResource\Pages;

use App\Filament\Resources\HrDepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrDepartment extends CreateRecord
{
    protected static string $resource = HrDepartmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = HrDepartmentResource::currentCompanyId();

        return $data;
    }
}
