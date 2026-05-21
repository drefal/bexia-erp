<?php

namespace App\Filament\Resources\AccountReceivableResource\Pages;

use App\Filament\Resources\AccountReceivableResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAccountReceivables extends ListRecords
{
    protected static string $resource = AccountReceivableResource::class;

    public function getTabs(): array
    {
        return [
            'pending_collection' => Tab::make('Pendientes de cobro')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['open', 'partial'])),

            'partial' => Tab::make('Cobro parcial')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'partial')),

            'paid' => Tab::make('Cobradas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'paid')),

            'cancelled' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),

            'all' => Tab::make('Todas'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
