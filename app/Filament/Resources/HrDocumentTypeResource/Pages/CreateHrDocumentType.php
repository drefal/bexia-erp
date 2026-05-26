<?php

namespace App\Filament\Resources\HrDocumentTypeResource\Pages;

use App\Filament\Resources\HrDocumentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrDocumentType extends CreateRecord
{
    protected static string $resource = HrDocumentTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = HrDocumentTypeResource::currentCompanyId();

        return $data;
    }
}
