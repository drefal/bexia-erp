<?php

namespace App\Filament\Resources\BillingPacConfigurationResource\Pages;

use App\Filament\Resources\BillingPacConfigurationResource;
use Filament\Resources\Pages\ListRecords;

class ListBillingPacConfigurations extends ListRecords
{
    protected static string $resource = BillingPacConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'PAC por empresa';
    }
}
