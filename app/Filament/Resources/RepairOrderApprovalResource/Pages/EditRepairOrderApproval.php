<?php

namespace App\Filament\Resources\RepairOrderApprovalResource\Pages;

use App\Filament\Resources\RepairOrderApprovalResource;
use Filament\Resources\Pages\EditRecord;

class EditRepairOrderApproval extends EditRecord
{
    protected static string $resource = RepairOrderApprovalResource::class;

    protected ?string $oldStatus = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->oldStatus = $this->record->status;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) !== $this->oldStatus && in_array(($data['status'] ?? null), ['aprobado', 'rechazado', 'cancelado'], true)) {
            $data['decided_by'] = auth()->id();
            $data['decided_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->oldStatus !== $this->record->status) {
            RepairOrderApprovalResource::logApprovalEvent(
                $this->record,
                'cambio_estado_aprobacion',
                'Cambio de estado de aprobacion desde Filament.'
            );

            return;
        }

        RepairOrderApprovalResource::logApprovalEvent(
            $this->record,
            'aprobacion_actualizada',
            'Aprobacion actualizada desde Filament.'
        );
    }
}
