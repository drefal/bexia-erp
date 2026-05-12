<?php

namespace App\Filament\Resources\SatCfdiCancellationReasonResource\Pages;

use App\Filament\Resources\SatCfdiCancellationReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSatCfdiCancellationReason extends EditRecord
{
    protected static string $resource = SatCfdiCancellationReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
