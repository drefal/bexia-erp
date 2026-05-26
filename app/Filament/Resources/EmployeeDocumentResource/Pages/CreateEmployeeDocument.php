<?php

namespace App\Filament\Resources\EmployeeDocumentResource\Pages;

use App\Filament\Resources\EmployeeDocumentResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeDocument extends CreateRecord
{
    protected static string $resource = EmployeeDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()?->getKey() ?? $data['company_id'] ?? null;
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
