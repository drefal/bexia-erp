<?php

namespace App\Filament\Resources\EmployeePayrollPerceptionResource\Pages;

use App\Filament\Resources\EmployeePayrollPerceptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePayrollPerceptions extends ListRecords
{
    protected static string $resource = EmployeePayrollPerceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva percepción'),
        ];
    }
}
