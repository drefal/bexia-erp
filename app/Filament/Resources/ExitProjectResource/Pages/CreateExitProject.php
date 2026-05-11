<?php

namespace App\Filament\Resources\ExitProjectResource\Pages;

use App\Filament\Resources\ExitProjectResource;
use App\Models\ExitProject;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateExitProject extends CreateRecord
{
    protected static string $resource = ExitProjectResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $data['company_id'] = (int) $tenantId;
        }

        if (blank($data['code'] ?? null) && $tenantId) {
            $data['code'] = ExitProject::nextCodeForCompany((int) $tenantId);
        }

        return $data;
    }
}
