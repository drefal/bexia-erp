<?php

namespace App\Filament\Resources\EmployeeTerminationResource\Pages;

use App\Filament\Resources\EmployeeTerminationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeTerminations extends ListRecords
{
    protected static string $resource = EmployeeTerminationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva baja'),
        ];
    }
}
