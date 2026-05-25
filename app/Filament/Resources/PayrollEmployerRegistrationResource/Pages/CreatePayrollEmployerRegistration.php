<?php

namespace App\Filament\Resources\PayrollEmployerRegistrationResource\Pages;

use App\Filament\Resources\PayrollEmployerRegistrationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollEmployerRegistration extends CreateRecord
{
    protected static string $resource = PayrollEmployerRegistrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = PayrollEmployerRegistrationResource::currentCompanyId();

        return $data;
    }
}
