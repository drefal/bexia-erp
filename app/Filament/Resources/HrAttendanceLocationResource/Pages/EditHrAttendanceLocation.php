<?php

namespace App\Filament\Resources\HrAttendanceLocationResource\Pages;

use App\Filament\Resources\HrAttendanceLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHrAttendanceLocation extends EditRecord
{
    protected static string $resource = HrAttendanceLocationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return HrAttendanceLocationResource::mutateFormDataBeforeSave($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
