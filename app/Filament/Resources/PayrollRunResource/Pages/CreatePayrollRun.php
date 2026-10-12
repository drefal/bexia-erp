<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()?->getKey() ?? $data['company_id'] ?? null;

        $periodType = PayrollRunResource::periodTypeForPayrollGroup(
            isset($data['payroll_group_id'])
                ? (int) $data['payroll_group_id']
                : null
        );

        if ($periodType !== null) {
            $data['period_type'] = $periodType;
        }

        /*
         * Snapshot histórico.
         * El modo se toma del grupo solamente al crear la corrida.
         */
        $data['calculation_mode_snapshot'] =
            PayrollRunResource::calculationModeForPayrollGroup(
                isset($data['payroll_group_id'])
                    ? (int) $data['payroll_group_id']
                    : null
            );

        $data['status'] = 'draft';
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
