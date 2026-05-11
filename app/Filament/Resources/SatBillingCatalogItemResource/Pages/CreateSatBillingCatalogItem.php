<?php

namespace App\Filament\Resources\SatBillingCatalogItemResource\Pages;

use App\Filament\Resources\SatBillingCatalogItemResource;
use App\Models\SatBillingCatalog;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSatBillingCatalogItem extends CreateRecord
{
    protected static string $resource = SatBillingCatalogItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $catalog = SatBillingCatalog::query()
            ->firstOrCreate(
                ['catalog_key' => $data['catalog_key']],
                ['name' => $data['catalog_key'], 'is_active' => true]
            );

        $data['catalog_id'] = $catalog->id;
        $data['external_key'] = $data['external_key'] ?? sha1($data['catalog_key'] . '|' . $data['code'] . '|' . now()->timestamp);

        $modelClass = static::getModel();

        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        return $record;
    }
}
