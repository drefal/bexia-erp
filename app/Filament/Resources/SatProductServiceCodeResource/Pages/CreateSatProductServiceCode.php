<?php

namespace App\Filament\Resources\SatProductServiceCodeResource\Pages;

use App\Filament\Resources\SatProductServiceCodeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSatProductServiceCode extends CreateRecord
{
    protected static string $resource = SatProductServiceCodeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = static::getModel();

        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        return $record;
    }
}
