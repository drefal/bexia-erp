<?php

namespace App\Filament\Resources\HrJobPositionResource\Pages;

use App\Filament\Resources\HrJobPositionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrJobPosition extends CreateRecord
{
    protected static string $resource = HrJobPositionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = HrJobPositionResource::currentCompanyId();

        return $data;
    }
}
