<?php

namespace App\Filament\Resources\EmployeeDocumentResource\Pages;

use App\Filament\Resources\EmployeeDocumentResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeDocument extends EditRecord
{
    protected static string $resource = EmployeeDocumentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
