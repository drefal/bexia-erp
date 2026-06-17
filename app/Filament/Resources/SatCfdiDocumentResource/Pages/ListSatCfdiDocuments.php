<?php

namespace App\Filament\Resources\SatCfdiDocumentResource\Pages;

use App\Filament\Resources\SatCfdiDocumentResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSatCfdiDocuments extends ListRecords
{
    protected static string $resource = SatCfdiDocumentResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),

            'received' => Tab::make('Recibidos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('direction', 'received')
                    ->where(function (Builder $query): void {
                        $query->whereNull('status')
                            ->orWhere('status', '!=', 'cancelado');
                    })),

            'issued' => Tab::make('Emitidos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('direction', 'issued')
                    ->where(function (Builder $query): void {
                        $query->whereNull('status')
                            ->orWhere('status', '!=', 'cancelado');
                    })),

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'cancelado')),
        ];
    }
}
