<?php

namespace App\Filament\Resources\PettyCashFundResource\Pages;

use App\Filament\Resources\PettyCashFundResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPettyCashFund extends ViewRecord
{
    protected static string $resource = PettyCashFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
