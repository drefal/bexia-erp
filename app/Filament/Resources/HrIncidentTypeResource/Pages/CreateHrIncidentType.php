<?php

namespace App\Filament\Resources\HrIncidentTypeResource\Pages;

use App\Filament\Resources\HrIncidentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrIncidentType extends CreateRecord
{
    protected static string $resource = HrIncidentTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = HrIncidentTypeResource::currentCompanyId();

        return $data;
    }
}
