<?php

namespace App\Filament\Resources\SatCompanyCredentialResource\Pages;

use App\Filament\Resources\SatCompanyCredentialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSatCompanyCredential extends EditRecord
{
    protected static string $resource = SatCompanyCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
