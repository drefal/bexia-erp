<?php

namespace App\Filament\Resources\PayrollGroupResource\Pages;

use App\Filament\Resources\PayrollGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollGroups extends ListRecords
{
    protected static string $resource = PayrollGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
