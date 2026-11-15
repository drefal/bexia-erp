<?php

namespace App\Filament\Resources\CustomBalloonExtraResource\Pages;

use App\Filament\Resources\CustomBalloonExtraResource;
use App\Support\CustomBalloonsAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomBalloonExtra extends CreateRecord
{
    protected static string $resource =
        CustomBalloonExtraResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $data['company_id'] =
            CustomBalloonsAccess::COMPANY_ID;

        if (($data['pricing_type'] ?? '') === 'manual') {
            $data['price'] = 0;
        }

        return $data;
    }
}
