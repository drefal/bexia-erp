<?php

namespace App\Filament\Resources\EmployeeDocumentResource\Pages;

use App\Filament\Resources\EmployeeDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeDocuments extends ListRecords
{
    protected static string $resource = EmployeeDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo documento'),
        ];
    }
}
