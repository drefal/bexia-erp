<?php

namespace App\Filament\Resources\TaxRateResource\Pages;

use App\Filament\Resources\TaxRateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = static::getModel();

        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        return $record;
    }
}
