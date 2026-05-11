<?php

namespace App\Filament\Resources\SatUnitCodeResource\Pages;

use App\Filament\Resources\SatUnitCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSatUnitCode extends EditRecord
{
    protected static string $resource = SatUnitCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar'),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data);
        $record->save();

        return $record;
    }
}
