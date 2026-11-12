<?php

namespace App\Filament\Resources\CommerceConnectorVersionResource\Pages;

use App\Filament\Resources\CommerceConnectorVersionResource;
use Filament\Resources\Pages\EditRecord;

class EditCommerceConnectorVersion extends EditRecord
{
    protected static string $resource =
        CommerceConnectorVersionResource::class;

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        // Activacion reservada al futuro proceso de certificacion.
        unset($data['is_enabled']);

        return $data;
    }
}
