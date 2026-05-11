<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Components\Tab;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo producto'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'productos' => Tab::make('Productos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $query): void {
                        $query
                            ->where('is_variant', false)
                            ->orWhereNull('is_variant');
                    })
                ),

            'variantes' => Tab::make('Variantes')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_variant', true)
                ),

            'todos' => Tab::make('Todos'),
        ];
    }

}
