<?php

namespace App\Filament\Resources\PayrollEmploymentSubsidyRuleResource\Pages;

use App\Filament\Resources\PayrollEmploymentSubsidyRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditPayrollEmploymentSubsidyRule extends EditRecord
{
    protected static string $resource =
        PayrollEmploymentSubsidyRuleResource::class;

    public function getTitle(): string
    {
        return 'Editar regla de subsidio al empleo';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
