<?php

namespace App\Filament\Resources\HrWorkScheduleResource\Pages;

use App\Filament\Resources\HrWorkScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHrWorkSchedule extends EditRecord
{
    protected static string $resource = HrWorkScheduleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return HrWorkScheduleResource::applyCalculatedHours($data, $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
