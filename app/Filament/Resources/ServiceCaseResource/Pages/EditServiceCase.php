<?php

namespace App\Filament\Resources\ServiceCaseResource\Pages;

use App\Filament\Resources\ServiceCaseResource;
use App\Support\Service\ServiceAccess;
use Filament\Resources\Pages\EditRecord;

class EditServiceCase extends EditRecord
{
    protected static string $resource = ServiceCaseResource::class;

    protected ?string $oldStatus = null;

    protected mixed $oldAssignedEmployeeId = null;

    protected mixed $uploadedAttachments = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->oldStatus = $this->record->status;
        $this->oldAssignedEmployeeId = $this->record->assigned_employee_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->uploadedAttachments = $data['uploaded_attachments'] ?? [];
        unset($data['uploaded_attachments']);

        if (
            array_key_exists('assigned_employee_id', $data)
            && (string) ($data['assigned_employee_id'] ?? '') !== (string) ($this->oldAssignedEmployeeId ?? '')
            && auth()->check()
        ) {
            $data['assigned_by'] = auth()->id();
            $data['assigned_at'] = now();
        }

        if (empty($data['product_name']) && ! empty($data['product_id'])) {
            $data['product_name'] = ServiceAccess::productLabel((int) $data['product_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        ServiceAccess::saveUploadedAttachments(
            companyId: $this->record->company_id,
            serviceCaseId: $this->record->id,
            repairOrderId: null,
            files: $this->uploadedAttachments,
            stage: 'ticket'
        );

        if ($this->oldStatus !== $this->record->status) {
            ServiceCaseResource::logEvent(
                $this->record,
                'cambio_estado_ticket',
                $this->oldStatus,
                $this->record->status,
                'Cambio de estado desde Filament.'
            );

            return;
        }

        if ((string) ($this->oldAssignedEmployeeId ?? '') !== (string) ($this->record->assigned_employee_id ?? '')) {
            ServiceCaseResource::logEvent(
                $this->record,
                'reasignacion_ticket',
                $this->record->status,
                $this->record->status,
                'Cambio de responsable desde Filament.'
            );

            return;
        }

        ServiceCaseResource::logEvent(
            $this->record,
            'ticket_actualizado',
            $this->record->status,
            $this->record->status,
            'Ticket actualizado desde Filament.'
        );
    }
}
