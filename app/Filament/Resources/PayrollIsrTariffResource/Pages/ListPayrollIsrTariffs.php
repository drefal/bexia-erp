<?php

namespace App\Filament\Resources\PayrollIsrTariffResource\Pages;

use App\Filament\Resources\PayrollIsrTariffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollIsrTariffs extends ListRecords
{
    protected static string $resource =
        PayrollIsrTariffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva tarifa ISR'),
        ];
    }
}
