<?php

namespace App\Filament\Resources\StockQuantResource\Pages;

use App\Filament\Resources\StockQuantResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockQuants extends ListRecords
{
    protected static string $resource = StockQuantResource::class;

    public function getTabs(): array
    {
        return [
            'positivas' => Tab::make('Con existencia')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where('quantity', '>', 0)
                ),

            'cero' => Tab::make('En cero')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where('quantity', '=', 0)
                ),

            'negativas' => Tab::make('Negativas')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where('quantity', '<', 0)
                ),

            'todas' => Tab::make('Todas')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query
                ),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'positivas';
    }

}
