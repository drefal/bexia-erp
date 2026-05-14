<?php

/* BEXIA_V5525K2_HIDE_TECHNICAL_CFDI_ACTIONS */

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva factura'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'todas';
    }

    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas'),

            'borrador' => Tab::make('Borrador')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'draft')),

            'facturadas' => Tab::make('Facturadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'issued')),

            'canceladas' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),
        ];
    }
}
