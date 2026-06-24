<?php

namespace App\Filament\Resources\HrAttendanceLocationResource\Pages;

use App\Filament\Resources\HrAttendanceLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHrAttendanceLocation extends CreateRecord
{
    protected static string $resource = HrAttendanceLocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return HrAttendanceLocationResource::mutateFormDataBeforeCreate($data);
    }
}
