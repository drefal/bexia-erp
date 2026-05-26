<?php

namespace App\Filament\Resources\EmployeeVacationBalanceResource\Pages;

use App\Filament\Resources\EmployeeVacationBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeVacationBalances extends ListRecords
{
    protected static string $resource = EmployeeVacationBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo saldo'),
        ];
    }
}
