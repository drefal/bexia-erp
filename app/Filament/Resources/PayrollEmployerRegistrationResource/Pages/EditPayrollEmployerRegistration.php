<?php

namespace App\Filament\Resources\PayrollEmployerRegistrationResource\Pages;

use App\Filament\Resources\PayrollEmployerRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollEmployerRegistration extends EditRecord
{
    protected static string $resource = PayrollEmployerRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
