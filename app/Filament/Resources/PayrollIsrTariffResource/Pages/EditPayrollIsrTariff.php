<?php

namespace App\Filament\Resources\PayrollIsrTariffResource\Pages;

use App\Filament\Resources\PayrollIsrTariffResource;
use Filament\Resources\Pages\EditRecord;

class EditPayrollIsrTariff extends EditRecord
{
    protected static string $resource =
        PayrollIsrTariffResource::class;

    public function getTitle(): string
    {
        return 'Editar tarifa ISR';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
