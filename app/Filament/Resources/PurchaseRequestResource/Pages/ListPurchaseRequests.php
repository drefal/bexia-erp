<?php

namespace App\Filament\Resources\PurchaseRequestResource\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva solicitud'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'draft',
                    'pending',
                    'submitted',
                    'requested',
                    'waiting_approval',
                    'approval_pending',
                ])),

            'approved' => Tab::make('Aprobadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'approved',
                    'authorized',
                ])),

            'converted' => Tab::make('Convertidas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'converted',
                    'ordered',
                    'purchase_order_created',
                ])),

            'rejected' => Tab::make('Rechazadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'rejected',
                    'denied',
                ])),

            'cancelled' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'cancelled',
                    'canceled',
                ])),

            'all' => Tab::make('Todas'),
        ];
    }


}
