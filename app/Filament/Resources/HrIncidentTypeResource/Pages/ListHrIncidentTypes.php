<?php

namespace App\Filament\Resources\HrIncidentTypeResource\Pages;

use App\Filament\Resources\HrIncidentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHrIncidentTypes extends ListRecords
{
    protected static string $resource = HrIncidentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
