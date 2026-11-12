<?php

namespace App\Filament\Resources\CommerceChannelResource\Pages;

use App\Filament\Resources\CommerceChannelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommerceChannel extends CreateRecord
{
    protected static string $resource =
        CommerceChannelResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        // Nunca activar un conector por crear su catalogo.
        $data['is_active'] = false;
        $data['lifecycle_status'] =
            $data['lifecycle_status'] ?? 'draft';

        return $data;
    }
}
