<?php

namespace App\Filament\Resources\SatCfdiUseTaxRegimeResource\Pages;

use App\Filament\Resources\SatCfdiUseTaxRegimeResource;
use Filament\Resources\Pages\ListRecords;

class ListSatCfdiUseTaxRegimes extends ListRecords
{
    protected static string $resource = SatCfdiUseTaxRegimeResource::class;

    public function getTitle(): string
    {
        return 'Uso CFDI por régimen';
    }
}
