<?php

namespace App\Filament\Resources\CommerceAuthFieldResource\Pages;

use App\Filament\Resources\CommerceAuthFieldResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCommerceAuthField extends EditRecord
{
    protected static string $resource =
        CommerceAuthFieldResource::class;

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        $versionId = (int)
            $this->record->commerce_connector_version_id;

        if (! CommerceAuthFieldResource::versionIsEditable(
            $versionId
        )) {
            throw ValidationException::withMessages([
                'data.commerce_connector_version_id' =>
                    'La versión está bloqueada.',
            ]);
        }

        unset(
            $data['commerce_connector_version_id'],
            $data['field_key']
        );

        return $data;
    }
}
