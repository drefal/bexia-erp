<?php

namespace App\Filament\Resources\PayrollEmploymentSubsidyRuleResource\Pages;

use App\Filament\Resources\PayrollEmploymentSubsidyRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollEmploymentSubsidyRule extends CreateRecord
{
    protected static string $resource =
        PayrollEmploymentSubsidyRuleResource::class;

    public function getTitle(): string
    {
        return 'Nueva regla de subsidio al empleo';
    }
}
