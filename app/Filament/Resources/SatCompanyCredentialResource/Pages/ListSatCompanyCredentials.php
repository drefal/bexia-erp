<?php

namespace App\Filament\Resources\SatCompanyCredentialResource\Pages;

use App\Filament\Resources\SatCompanyCredentialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSatCompanyCredentials extends ListRecords
{
    protected static string $resource = SatCompanyCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva configuración SAT'),
        ];
    }
}
