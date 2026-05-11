<?php

namespace App\Filament\Resources\SatBillingCatalogItemResource\Pages;

use App\Filament\Resources\SatBillingCatalogItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSatBillingCatalogItem extends EditRecord
{
    protected static string $resource = SatBillingCatalogItemResource::class;

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
