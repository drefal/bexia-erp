<?php

namespace App\Filament\Resources\HrWorkScheduleResource\Pages;

use App\Filament\Resources\HrWorkScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrWorkSchedule extends CreateRecord
{
    protected static string $resource = HrWorkScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = HrWorkScheduleResource::currentCompanyId();

        return $data;
    }
}
