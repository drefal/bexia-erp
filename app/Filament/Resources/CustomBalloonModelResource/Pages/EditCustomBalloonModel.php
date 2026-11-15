<?php

namespace App\Filament\Resources\CustomBalloonModelResource\Pages;

use App\Filament\Resources\CustomBalloonModelResource;
use App\Support\CustomBalloonsAccess;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomBalloonModel extends EditRecord
{
    protected static string $resource = CustomBalloonModelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['company_id'] = CustomBalloonsAccess::COMPANY_ID;

        return $data;
    }
}
