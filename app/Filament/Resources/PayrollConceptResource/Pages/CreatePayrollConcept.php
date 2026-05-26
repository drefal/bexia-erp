<?php

namespace App\Filament\Resources\PayrollConceptResource\Pages;

use App\Filament\Resources\PayrollConceptResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollConcept extends CreateRecord
{
    protected static string $resource = PayrollConceptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $data['company_id'] = $tenantId;
        }

        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
