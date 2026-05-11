<?php

namespace App\Filament\Resources\SatUnitCodeResource\Pages;

use App\Filament\Resources\SatUnitCodeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSatUnitCode extends CreateRecord
{
    protected static string $resource = SatUnitCodeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = static::getModel();

        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        return $record;
    }
}
