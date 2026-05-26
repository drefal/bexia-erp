<?php

namespace App\Filament\Resources\EmployeeTerminationResource\Pages;

use App\Filament\Resources\EmployeeTerminationResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeTermination extends CreateRecord
{
    protected static string $resource = EmployeeTerminationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()?->getKey() ?? $data['company_id'] ?? null;
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_by_user_id'] = auth()->id();
            $data['completed_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        EmployeeTerminationResource::applyTermination($this->record, auth()->id());
    }
}
