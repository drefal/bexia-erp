<?php

namespace App\Filament\Resources\PayrollPeriodicityResource\Pages;

use App\Filament\Resources\PayrollPeriodicityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollPeriodicities extends ListRecords
{
    protected static string $resource = PayrollPeriodicityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
