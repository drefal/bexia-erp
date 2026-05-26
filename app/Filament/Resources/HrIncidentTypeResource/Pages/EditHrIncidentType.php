<?php

namespace App\Filament\Resources\HrIncidentTypeResource\Pages;

use App\Filament\Resources\HrIncidentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHrIncidentType extends EditRecord
{
    protected static string $resource = HrIncidentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
