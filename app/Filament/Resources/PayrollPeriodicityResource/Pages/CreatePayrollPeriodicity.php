<?php

namespace App\Filament\Resources\PayrollPeriodicityResource\Pages;

use App\Filament\Resources\PayrollPeriodicityResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollPeriodicity extends CreateRecord
{
    protected static string $resource = PayrollPeriodicityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = PayrollPeriodicityResource::currentCompanyId();

        return $data;
    }
}
