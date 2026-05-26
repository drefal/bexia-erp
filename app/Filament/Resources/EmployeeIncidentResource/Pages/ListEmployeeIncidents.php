<?php

namespace App\Filament\Resources\EmployeeIncidentResource\Pages;

use App\Filament\Resources\EmployeeIncidentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeIncidents extends ListRecords
{
    protected static string $resource = EmployeeIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva incidencia'),
        ];
    }
}
