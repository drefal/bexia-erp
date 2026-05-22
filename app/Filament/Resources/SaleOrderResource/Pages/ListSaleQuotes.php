<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ListSaleQuotes extends ListRecords
{
    protected static string $resource = SaleOrderResource::class;

    public function getTitle(): string
    {
        return 'Cotizaciones';
    }

    public function getBreadcrumb(): string
    {
        return 'Cotizaciones';
    }

    public function getBreadcrumbs(): array
    {
        return [
            SaleOrderResource::getUrl('quotes') => 'Cotizaciones',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva cotización'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    public function getTabs(): array
    {
        return [
            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'draft',
                    'quotation',
                    'quote',
                ])),

            'approval_pending' => Tab::make('Pendientes de aprobación')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->quoteBaseQuery($query)
                    ->where(function (Builder $query): void {
                        $this->approvalStatusCondition($query, ['pending', 'required']);
                    })
                ),

            'approval_approved' => Tab::make('Aprobadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->quoteBaseQuery($query)
                    ->where(function (Builder $query): void {
                        $this->approvalStatusCondition($query, ['approved']);
                    })
                ),

            'approval_rejected' => Tab::make('Rechazadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->quoteBaseQuery($query)
                    ->where(function (Builder $query): void {
                        $this->approvalStatusCondition($query, ['rejected', 'denied']);
                    })
                ),

            'cancelled' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->quoteBaseQuery($query)
                    ->whereIn('status', ['cancelled', 'canceled'])
                ),

            'all' => Tab::make('Todas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->quoteBaseQuery($query)),
        ];
    }

    protected function quoteBaseQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('status', [
                'draft',
                'quotation',
                'quote',
                'cancelled',
                'canceled',
            ]);

            if (Schema::hasColumn('sales_orders', 'margin_approval_status')) {
                $query->orWhereIn('margin_approval_status', [
                    'required',
                    'pending',
                    'approved',
                    'rejected',
                ]);
            }

            if (Schema::hasColumn('sales_orders', 'margin_approval_required')) {
                $query->orWhere('margin_approval_required', true);
            }

            if (Schema::hasTable('approval_requests')) {
                $query->orWhereExists(function ($subquery): void {
                    $subquery
                        ->selectRaw('1')
                        ->from('approval_requests')
                        ->whereColumn('approval_requests.approvable_id', 'sales_orders.id')
                        ->whereIn('approval_requests.document_type', [
                            'sales_quote',
                            'sales_margin_approval',
                        ]);
                });
            }
        });
    }

    protected function approvalStatusCondition(Builder $query, array $statuses): void
    {
        if (Schema::hasColumn('sales_orders', 'margin_approval_status')) {
            $query->orWhereIn('margin_approval_status', $statuses);
        }

        if (Schema::hasTable('approval_requests')) {
            $query->orWhereExists(function ($subquery) use ($statuses): void {
                $subquery
                    ->selectRaw('1')
                    ->from('approval_requests')
                    ->whereColumn('approval_requests.approvable_id', 'sales_orders.id')
                    ->whereIn('approval_requests.document_type', [
                        'sales_quote',
                        'sales_margin_approval',
                    ])
                    ->whereIn('approval_requests.status', $statuses);
            });
        }
    }
}
