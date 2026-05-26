<?php

namespace App\Filament\Resources\EmployeeIncidentResource\Pages;

use App\Filament\Resources\EmployeeIncidentResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeIncident extends EditRecord
{
    protected static string $resource = EmployeeIncidentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        if (($data['status'] ?? null) === 'approved' && blank($this->record->approved_at)) {
            $data['approved_by_user_id'] = auth()->id();
            $data['approved_at'] = now();
        }

        return $data;
    }
}
