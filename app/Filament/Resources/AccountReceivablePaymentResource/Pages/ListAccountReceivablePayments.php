<?php

namespace App\Filament\Resources\AccountReceivablePaymentResource\Pages;

use App\Filament\Resources\AccountReceivablePaymentResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAccountReceivablePayments extends ListRecords
{
    protected static string $resource = AccountReceivablePaymentResource::class;

    public function getTabs(): array
    {
        return [
            'posted' => Tab::make('Aplicados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'posted')),

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),

            'all' => Tab::make('Todos'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
