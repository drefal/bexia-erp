<?php

namespace App\Filament\Resources\StockReplenishmentRuleResource\Pages;

use App\Filament\Resources\StockReplenishmentRuleResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockReplenishmentRules extends ListRecords
{
    protected static string $resource = StockReplenishmentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva regla'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'activas' => Tab::make('Activas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true)),

            'inactivas' => Tab::make('Inactivas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', false)),

            'todas' => Tab::make('Todas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'activas';
    }
}
