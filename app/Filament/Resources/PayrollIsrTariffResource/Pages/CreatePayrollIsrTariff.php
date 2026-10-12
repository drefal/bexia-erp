<?php

namespace App\Filament\Resources\PayrollIsrTariffResource\Pages;

use App\Filament\Resources\PayrollIsrTariffResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollIsrTariff extends CreateRecord
{
    protected static string $resource =
        PayrollIsrTariffResource::class;

    public function getTitle(): string
    {
        return 'Nueva tarifa ISR';
    }
}
