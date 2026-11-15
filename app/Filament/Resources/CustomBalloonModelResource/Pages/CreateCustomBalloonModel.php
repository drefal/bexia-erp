<?php

namespace App\Filament\Resources\CustomBalloonModelResource\Pages;

use App\Filament\Resources\CustomBalloonModelResource;
use App\Support\CustomBalloonsAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomBalloonModel extends CreateRecord
{
    protected static string $resource = CustomBalloonModelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        // AUTO_SORT_ORDER_GLB
        $data['sort_order'] =
            (int) \App\Models\CustomBalloonModel::query()
                ->where('company_id', 3)
                ->max('sort_order') + 10;


        $data['company_id'] = CustomBalloonsAccess::COMPANY_ID;

        return $data;
    }
}
