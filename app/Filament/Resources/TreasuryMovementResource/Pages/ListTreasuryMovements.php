<?php

namespace App\Filament\Resources\TreasuryMovementResource\Pages;

use App\Filament\Resources\TreasuryMovementResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTreasuryMovements extends ListRecords
{
    protected static string $resource = TreasuryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear movimiento de tesorería'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),

            'draft' => Tab::make('Borrador')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'draft')),

            'posted' => Tab::make('Confirmados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'posted')),

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),
        ];
    }
}
