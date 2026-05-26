<?php

namespace App\Filament\Resources\HrDepartmentResource\Pages;

use App\Filament\Resources\HrDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHrDepartments extends ListRecords
{
    protected static string $resource = HrDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
