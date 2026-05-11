<?php

namespace App\Filament\Resources\PosCashierResource\Pages;

use App\Filament\Resources\PosCashierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosCashier extends EditRecord
{
    protected static string $resource = PosCashierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
