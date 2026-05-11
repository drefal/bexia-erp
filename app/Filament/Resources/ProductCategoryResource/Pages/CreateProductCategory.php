<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = static::getModel();

        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        return $record;
    }
}
