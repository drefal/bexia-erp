<?php

namespace App\Filament\Resources\StockLocationResource\Pages;

use App\Filament\Resources\StockLocationResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockLocations extends ListRecords
{
    protected static string $resource = StockLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva ubicación'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'existencias' => Tab::make('Existencias')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->whereNotNull('warehouse_id')
                    ->whereHas('type', fn (Builder $query): Builder => $query->where('is_internal', true))),

            'virtuales' => Tab::make('Virtuales')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->whereNull('warehouse_id')),

            'todas' => Tab::make('Todas activas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_active', true)),

            'inactivas' => Tab::make('Inactivas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_active', false)),
        ];
    }
}
