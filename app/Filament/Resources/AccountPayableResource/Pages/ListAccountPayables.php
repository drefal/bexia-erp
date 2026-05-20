<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Resources\AccountPayableResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAccountPayables extends ListRecords
{
    protected static string $resource = AccountPayableResource::class;

    public function getTabs(): array
    {
        return [
            'pending_payment' => Tab::make('Pendientes de pago')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['open', 'partial'])),

            'partial' => Tab::make('Pago parcial')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'partial')),

            'paid' => Tab::make('Pagadas')
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
