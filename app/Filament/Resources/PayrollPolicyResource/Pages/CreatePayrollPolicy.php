<?php

namespace App\Filament\Resources\PayrollPolicyResource\Pages;

use App\Filament\Resources\PayrollPolicyResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollPolicy extends CreateRecord
{
    protected static string $resource = PayrollPolicyResource::class;

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
