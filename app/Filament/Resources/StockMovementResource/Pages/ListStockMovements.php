<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo traslado'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'borradores' => Tab::make('Borradores')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'draft')),

            'hechos' => Tab::make('Hechos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'done')),

            'en_transito' => Tab::make('En tránsito')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'done')
                    ->where(function (Builder $query): void {
                        $query
                            ->whereHas('sourceLocation.type', fn (Builder $query): Builder => $query->where('code', 'TRANSIT'))
                            ->orWhereHas('destinationLocation.type', fn (Builder $query): Builder => $query->where('code', 'TRANSIT'));
                    })),

            'todos' => Tab::make('Todos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query),
        ];
    }
    public function getDefaultActiveTab(): string|int|null
    {
        return 'todos';
    }

}
