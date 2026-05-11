<?php

namespace App\Filament\Resources\StockOperationTypeResource\Pages;

use App\Filament\Resources\StockOperationTypeResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockOperationTypes extends ListRecords
{
    protected static string $resource = StockOperationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo tipo de operación'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'activos' => Tab::make('Activos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true)),

            'inactivos' => Tab::make('Inactivos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', false)),

            'todos' => Tab::make('Todos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query),
        ];
    }
}
