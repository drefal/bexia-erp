<?php

namespace App\Filament\Resources\EmployeeIncidentResource\Pages;

use App\Filament\Resources\EmployeeIncidentResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeIncident extends CreateRecord
{
    protected static string $resource = EmployeeIncidentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()?->getKey() ?? $data['company_id'] ?? null;
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        if (($data['status'] ?? null) === 'approved') {
            $data['approved_by_user_id'] = auth()->id();
            $data['approved_at'] = now();
        }

        return $data;
    }
}
