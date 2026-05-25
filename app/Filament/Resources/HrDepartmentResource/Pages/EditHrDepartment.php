<?php

namespace App\Filament\Resources\HrDepartmentResource\Pages;

use App\Filament\Resources\HrDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHrDepartment extends EditRecord
{
    protected static string $resource = HrDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
