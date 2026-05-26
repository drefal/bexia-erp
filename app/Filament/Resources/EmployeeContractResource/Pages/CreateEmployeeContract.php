<?php

namespace App\Filament\Resources\EmployeeContractResource\Pages;

use App\Filament\Resources\EmployeeContractResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeContract extends CreateRecord
{
    protected static string $resource = EmployeeContractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()?->getKey() ?? $data['company_id'] ?? null;
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        if (($data['status'] ?? null) === 'active') {
            $data['is_current'] = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        EmployeeContractResource::syncCurrentContract($this->record);
    }
}
