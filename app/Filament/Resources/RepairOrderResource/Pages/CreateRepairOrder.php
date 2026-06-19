<?php

namespace App\Filament\Resources\RepairOrderResource\Pages;

use App\Filament\Resources\RepairOrderResource;
use App\Support\Service\ServiceAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateRepairOrder extends CreateRecord
{
    protected static string $resource = RepairOrderResource::class;

    protected mixed $uploadedAttachments = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->uploadedAttachments = $data['uploaded_attachments'] ?? [];
        unset($data['uploaded_attachments']);

        $data['company_id'] = $data['company_id'] ?? ServiceAccess::currentCompanyId();

        if (auth()->check()) {
            $data['created_by'] = $data['created_by'] ?? auth()->id();

            if (! empty($data['assigned_employee_id'])) {
                $data['assigned_by'] = $data['assigned_by'] ?? auth()->id();
                $data['assigned_at'] = $data['assigned_at'] ?? now();
            }
        }

        if (empty($data['received_at'])) {
            $data['received_at'] = now();
        }

        if (empty($data['product_name']) && ! empty($data['product_id'])) {
            $data['product_name'] = ServiceAccess::productLabel((int) $data['product_id']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        RepairOrderResource::logEvent(
            $this->record,
            'reparacion_creada',
            null,
            $this->record->status,
            'Orden de reparacion creada desde Filament.'
        );

        ServiceAccess::saveUploadedAttachments(
            companyId: $this->record->company_id,
            serviceCaseId: $this->record->service_case_id,
            repairOrderId: $this->record->id,
            files: $this->uploadedAttachments,
            stage: 'reparacion'
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }

}
