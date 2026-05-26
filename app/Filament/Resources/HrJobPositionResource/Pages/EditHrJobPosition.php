<?php

namespace App\Filament\Resources\HrJobPositionResource\Pages;

use App\Filament\Resources\HrJobPositionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHrJobPosition extends EditRecord
{
    protected static string $resource = HrJobPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
