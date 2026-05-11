<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use App\Models\Company;
use App\Support\CompanyGroupLimits;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;

    protected function beforeCreate(): void
    {
        $company = Company::find($this->data['company_id'] ?? null);
        $group = CompanyGroupLimits::groupFromCompany($company);

        if (! CompanyGroupLimits::canAddBranch($group)) {
            Notification::make()
                ->title('Límite de sucursales alcanzado')
                ->body(CompanyGroupLimits::branchLimitMessage($group))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
