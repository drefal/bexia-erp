<?php

namespace App\Filament\Resources\SatUnitResource\Pages;

use App\Filament\Resources\SatUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSatUnit extends EditRecord
{
    protected static string $resource = SatUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar'),
        ];
    }
}
