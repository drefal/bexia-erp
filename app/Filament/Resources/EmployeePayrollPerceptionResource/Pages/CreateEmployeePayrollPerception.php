<?php

namespace App\Filament\Resources\EmployeePayrollPerceptionResource\Pages;

use App\Filament\Resources\EmployeePayrollPerceptionResource;
use App\Models\EmployeePayrollPerception;
use App\Models\PayrollConcept;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeePayrollPerception extends CreateRecord
{
    protected static string $resource = EmployeePayrollPerceptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $data['company_id'] = $tenantId;
        }

        $data['code'] = $data['code'] ?: EmployeePayrollPerception::defaultCodeForType((string) ($data['type'] ?? 'bonus'));
        $data['name'] = $data['name'] ?: (EmployeePayrollPerception::typeOptions()[(string) ($data['type'] ?? 'bonus')] ?? 'Percepción de empleado');

        if (blank($data['payroll_concept_id'] ?? null) && filled($data['company_id'] ?? null)) {
            $data['payroll_concept_id'] = PayrollConcept::query()
                ->where('company_id', $data['company_id'])
                ->where('code', $data['code'])
                ->value('id');
        }

        if ((float) ($data['remaining_amount'] ?? 0) <= 0) {
            $data['remaining_amount'] = (float) ($data['original_amount'] ?? 0);
        }

        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
