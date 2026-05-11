<?php

namespace App\Filament\Resources\ExitWarehouseResource\Pages;

use App\Filament\Resources\ExitWarehouseResource;
use App\Models\ExitWarehouse;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateExitWarehouse extends CreateRecord
{
    protected static string $resource = ExitWarehouseResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $data['company_id'] = (int) $tenantId;
        }

        if (blank($data['code'] ?? null) && $tenantId) {
            $data['code'] = ExitWarehouse::nextCodeForCompany((int) $tenantId);
        }

        return $data;
    }
}
