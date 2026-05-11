<?php

namespace App\Filament\Resources\SatProductServiceCodeResource\Pages;

use App\Filament\Resources\SatProductServiceCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSatProductServiceCode extends EditRecord
{
    protected static string $resource = SatProductServiceCodeResource::class;

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
