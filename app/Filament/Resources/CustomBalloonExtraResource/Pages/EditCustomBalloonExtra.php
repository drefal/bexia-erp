<?php

namespace App\Filament\Resources\CustomBalloonExtraResource\Pages;

use App\Filament\Resources\CustomBalloonExtraResource;
use App\Support\CustomBalloonsAccess;
use Filament\Resources\Pages\EditRecord;

class EditCustomBalloonExtra extends EditRecord
{
    protected static string $resource =
        CustomBalloonExtraResource::class;

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        $data['company_id'] =
            CustomBalloonsAccess::COMPANY_ID;

        if (($data['pricing_type'] ?? '') === 'manual') {
            $data['price'] = 0;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
