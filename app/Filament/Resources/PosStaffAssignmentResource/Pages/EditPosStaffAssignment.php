<?php

namespace App\Filament\Resources\PosStaffAssignmentResource\Pages;

use App\Filament\Resources\PosStaffAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosStaffAssignment extends EditRecord
{
    protected static string $resource = PosStaffAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
