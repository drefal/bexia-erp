<?php

namespace App\Filament\Resources\EmployeeTerminationResource\Pages;

use App\Filament\Resources\EmployeeTerminationResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeTermination extends EditRecord
{
    protected static string $resource = EmployeeTerminationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_by_user_id'] = $data['completed_by_user_id'] ?? auth()->id();
            $data['completed_at'] = $data['completed_at'] ?? now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        EmployeeTerminationResource::applyTermination($this->record, auth()->id());
    }
}
