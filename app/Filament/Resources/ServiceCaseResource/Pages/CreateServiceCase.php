<?php

namespace App\Filament\Resources\ServiceCaseResource\Pages;

use App\Filament\Resources\ServiceCaseResource;
use App\Support\Service\ServiceAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceCase extends CreateRecord
{
    protected static string $resource = ServiceCaseResource::class;

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

        if (empty($data['contact_name']) && ! empty($data['customer_id'])) {
            $data['contact_name'] = ServiceAccess::contactLabel((int) $data['customer_id']);
        }

        if (empty($data['product_name']) && ! empty($data['product_id'])) {
            $data['product_name'] = ServiceAccess::productLabel((int) $data['product_id']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        ServiceCaseResource::logEvent(
            $this->record,
            'ticket_creado',
            null,
            $this->record->status,
            'Ticket creado desde Filament.'
        );

        ServiceAccess::saveUploadedAttachments(
            companyId: $this->record->company_id,
            serviceCaseId: $this->record->id,
            repairOrderId: null,
            files: $this->uploadedAttachments,
            stage: 'ticket'
        );
    }
}
