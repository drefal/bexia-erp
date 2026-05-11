<?php

namespace App\Filament\Resources\StockReplenishmentRuleResource\Pages;

use App\Filament\Resources\StockReplenishmentRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockReplenishmentRule extends EditRecord
{
    protected static string $resource = StockReplenishmentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar'),
        ];
    }
}
