<?php

namespace App\Filament\Resources\EmployeePayrollPurchaseResource\Pages;

use App\Filament\Resources\EmployeePayrollPurchaseResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeePayrollPurchase extends CreateRecord
{
    protected static string $resource = EmployeePayrollPurchaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $data['company_id'] = (int) $tenantId;
        }

        $data['status'] = 'draft';
        $data['subtotal'] = 0;
        $data['tax_total'] = 0;
        $data['total_amount'] = 0;
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Compra guardada en borrador';
    }
}
