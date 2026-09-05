<?php

namespace App\Filament\Resources\AttendanceTerminalResource\Pages;

use App\Filament\Resources\AttendanceTerminalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceTerminals extends ListRecords
{
    protected static string $resource = AttendanceTerminalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Registrar terminal'),
        ];
    }
}
