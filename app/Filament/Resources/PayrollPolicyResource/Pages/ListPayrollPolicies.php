<?php

namespace App\Filament\Resources\PayrollPolicyResource\Pages;

use App\Filament\Resources\PayrollPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollPolicies extends ListRecords
{
    protected static string $resource = PayrollPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva política'),
        ];
    }
}
