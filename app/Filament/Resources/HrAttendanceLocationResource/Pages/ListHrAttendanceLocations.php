<?php

namespace App\Filament\Resources\HrAttendanceLocationResource\Pages;

use App\Filament\Resources\HrAttendanceLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHrAttendanceLocations extends ListRecords
{
    protected static string $resource = HrAttendanceLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
