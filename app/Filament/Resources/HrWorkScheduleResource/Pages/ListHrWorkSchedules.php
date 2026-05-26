<?php

namespace App\Filament\Resources\HrWorkScheduleResource\Pages;

use App\Filament\Resources\HrWorkScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHrWorkSchedules extends ListRecords
{
    protected static string $resource = HrWorkScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
