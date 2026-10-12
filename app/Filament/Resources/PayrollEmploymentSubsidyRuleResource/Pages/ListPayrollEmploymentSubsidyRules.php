<?php

namespace App\Filament\Resources\PayrollEmploymentSubsidyRuleResource\Pages;

use App\Filament\Resources\PayrollEmploymentSubsidyRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollEmploymentSubsidyRules extends ListRecords
{
    protected static string $resource =
        PayrollEmploymentSubsidyRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva regla'),
        ];
    }
}
