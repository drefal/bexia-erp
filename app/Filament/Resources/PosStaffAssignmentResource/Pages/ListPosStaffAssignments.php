<?php

namespace App\Filament\Resources\PosStaffAssignmentResource\Pages;

use App\Filament\Resources\PosStaffAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosStaffAssignments extends ListRecords
{
    protected static string $resource = PosStaffAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva asignación'),
        ];
    }
}
