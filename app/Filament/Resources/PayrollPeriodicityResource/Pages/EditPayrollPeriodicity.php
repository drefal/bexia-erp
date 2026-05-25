<?php

namespace App\Filament\Resources\PayrollPeriodicityResource\Pages;

use App\Filament\Resources\PayrollPeriodicityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollPeriodicity extends EditRecord
{
    protected static string $resource = PayrollPeriodicityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
