<?php

namespace App\Filament\Resources\EmployeeContractResource\Pages;

use App\Filament\Resources\EmployeeContractResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeContract extends EditRecord
{
    protected static string $resource = EmployeeContractResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        $data['is_current'] =
            (($data['status'] ?? 'draft') === 'active');

        return $data;
    }

    protected function afterSave(): void
    {
        EmployeeContractResource::syncCurrentContract($this->record);
    }
}
