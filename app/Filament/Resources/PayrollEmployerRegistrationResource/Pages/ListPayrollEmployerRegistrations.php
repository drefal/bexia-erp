<?php

namespace App\Filament\Resources\PayrollEmployerRegistrationResource\Pages;

use App\Filament\Resources\PayrollEmployerRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollEmployerRegistrations extends ListRecords
{
    protected static string $resource = PayrollEmployerRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
