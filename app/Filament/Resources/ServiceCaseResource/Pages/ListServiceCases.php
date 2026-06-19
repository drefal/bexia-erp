<?php

namespace App\Filament\Resources\ServiceCaseResource\Pages;

use App\Filament\Resources\ServiceCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceCases extends ListRecords
{
    protected static string $resource = ServiceCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear ticket de servicio')
                ->visible(fn (): bool => ServiceCaseResource::canCreate()),
        ];
    }
}
