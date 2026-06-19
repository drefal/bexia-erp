<?php

namespace App\Filament\Resources\RepairOrderApprovalResource\Pages;

use App\Filament\Resources\RepairOrderApprovalResource;
use App\Support\Service\ServiceAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateRepairOrderApproval extends CreateRecord
{
    protected static string $resource = RepairOrderApprovalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $data['company_id'] ?? ServiceAccess::currentCompanyId();
        $data['requested_by'] = $data['requested_by'] ?? auth()->id();
        $data['requested_at'] = $data['requested_at'] ?? now();

        return $data;
    }

    protected function afterCreate(): void
    {
        RepairOrderApprovalResource::logApprovalEvent(
            $this->record,
            'aprobacion_solicitada',
            'Solicitud de aprobacion creada desde Filament.'
        );
    }
}
