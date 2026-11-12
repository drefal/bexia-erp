<?php

namespace App\Filament\Resources\CommerceChannelResource\Pages;

use App\Filament\Resources\CommerceChannelResource;
use Filament\Resources\Pages\EditRecord;

class EditCommerceChannel extends EditRecord
{
    protected static string $resource =
        CommerceChannelResource::class;

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        // La UI de catalogo no autoriza activaciones.
        unset($data['is_active']);

        return $data;
    }
}
