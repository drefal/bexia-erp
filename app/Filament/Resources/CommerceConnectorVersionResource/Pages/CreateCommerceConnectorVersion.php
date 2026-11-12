<?php

namespace App\Filament\Resources\CommerceConnectorVersionResource\Pages;

use App\Filament\Resources\CommerceConnectorVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommerceConnectorVersion extends CreateRecord
{
    protected static string $resource =
        CommerceConnectorVersionResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        // Los registros nuevos nunca se activan automaticamente.
        $data['is_enabled'] = false;
        $data['status'] = $data['status'] ?? 'draft';

        return $data;
    }
}
