<?php

namespace App\Filament\Resources\CommerceAuthFieldResource\Pages;

use App\Filament\Resources\CommerceAuthFieldResource;
use App\Models\CommerceAuthField;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateCommerceAuthField extends CreateRecord
{
    protected static string $resource =
        CommerceAuthFieldResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $versionId = (int) (
            $data['commerce_connector_version_id'] ?? 0
        );

        if (! CommerceAuthFieldResource::versionIsEditable(
            $versionId
        )) {
            throw ValidationException::withMessages([
                'data.commerce_connector_version_id' =>
                    'La versión no admite cambios.',
            ]);
        }

        $fieldKey = (string) ($data['field_key'] ?? '');

        if (CommerceAuthField::query()
            ->where('commerce_connector_version_id', $versionId)
            ->where('field_key', $fieldKey)
            ->exists()) {
            throw ValidationException::withMessages([
                'data.field_key' =>
                    'Esta clave ya existe para la versión.',
            ]);
        }

        return $data;
    }
}
