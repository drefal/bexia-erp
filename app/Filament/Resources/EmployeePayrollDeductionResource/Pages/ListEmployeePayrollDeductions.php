<?php

namespace App\Filament\Resources\EmployeePayrollDeductionResource\Pages;

use App\Filament\Resources\EmployeePayrollDeductionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePayrollDeductions extends ListRecords
{
    protected static string $resource = EmployeePayrollDeductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo descuento'),
        ];
    }
}
