<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\CompanyGroup;
use App\Support\CompanyGroupLimits;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function beforeCreate(): void
    {
        $group = CompanyGroup::find($this->data['company_group_id'] ?? null);

        if (! CompanyGroupLimits::canAddCompany($group)) {
            Notification::make()
                ->title('Límite de empresas alcanzado')
                ->body(CompanyGroupLimits::companyLimitMessage($group))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
