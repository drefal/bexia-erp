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
        $notSentOrPaidInPos = function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
            return $query->where(function (\Illuminate\Database\Eloquent\Builder $sub): void {
                if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
                    $sub->whereNull('quote_pos_payment_status')
                        ->orWhereNotIn('quote_pos_payment_status', ['sent', 'paid']);
                }
            });
        };

        return [
            'draft' => \Filament\Resources\Components\Tab::make('Borradores')
                ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) use ($notSentOrPaidInPos): \Illuminate\Database\Eloquent\Builder {
                    return $notSentOrPaidInPos(
                        $query->where('status', 'draft')
                    );
                }),

            'pending_approval' => \Filament\Resources\Components\Tab::make('Pendientes de aprobación')
                ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) use ($notSentOrPaidInPos): \Illuminate\Database\Eloquent\Builder {
                    return $notSentOrPaidInPos(
                        $query->where('margin_approval_status', 'pending')
                    );
                }),

            'approved' => \Filament\Resources\Components\Tab::make('Aprobadas')
                ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) use ($notSentOrPaidInPos): \Illuminate\Database\Eloquent\Builder {
                    return $notSentOrPaidInPos(
                        $query->where('margin_approval_status', 'approved')
                    );
                }),

            'rejected' => \Filament\Resources\Components\Tab::make('Rechazadas')
                ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) use ($notSentOrPaidInPos): \Illuminate\Database\Eloquent\Builder {
                    return $notSentOrPaidInPos(
                        $query->where('margin_approval_status', 'rejected')
                    );
                }),

            'cancelled' => \Filament\Resources\Components\Tab::make('Canceladas')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query
                    ->where('status', 'cancelled')),

            'sent_to_pos' => \Filament\Resources\Components\Tab::make('Enviadas a PDV')
                ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                    return $query->where(function (\Illuminate\Database\Eloquent\Builder $sub): void {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
                            $sub->where('quote_pos_payment_status', 'sent');
                        }

                        if (\Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
                            $sub->orWhereExists(function ($exists): void {
                                $exists->select(\Illuminate\Support\Facades\DB::raw(1))
                                    ->from('sales_quote_pos_tickets as sqpt')
                                    ->whereColumn('sqpt.sales_order_id', 'sales_orders.id')
                                    ->whereIn('sqpt.status', ['pending', 'sent']);
                            });
                        }
                    });
                }),

            'paid_in_pos' => \Filament\Resources\Components\Tab::make('Cobradas en PDV')
                ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                    return $query->where(function (\Illuminate\Database\Eloquent\Builder $sub): void {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
                            $sub->where('quote_pos_payment_status', 'paid');
                        }

                        if (\Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
                            $sub->orWhereExists(function ($exists): void {
                                $exists->select(\Illuminate\Support\Facades\DB::raw(1))
                                    ->from('sales_quote_pos_tickets as sqpt')
                                    ->whereColumn('sqpt.sales_order_id', 'sales_orders.id')
                                    ->where('sqpt.status', 'paid');
                            });
                        }
                    });
                }),

            'all' => \Filament\Resources\Components\Tab::make('Todas'),
        ];
    }


    protected function quoteBaseQuery(Builder $query): Builder
    {
        // V5.61.2b: cotizaciones excluye ordenes confirmadas.
        // Las ordenes de venta viven en su propia seccion.
        $query->whereNotIn('status', ['confirmed', 'delivered', 'partially_delivered']);

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
