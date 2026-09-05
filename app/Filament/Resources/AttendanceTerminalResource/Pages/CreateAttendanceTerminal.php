<?php

namespace App\Filament\Resources\AttendanceTerminalResource\Pages;

use App\Filament\Resources\AttendanceTerminalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceTerminal extends CreateRecord
{
    protected static string $resource = AttendanceTerminalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AttendanceTerminalResource::prepareDataForPersistence(
            $data,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancelar')
                ->url(
                    static::getResource()::getUrl('index')
                ),
        ];
    }
}
