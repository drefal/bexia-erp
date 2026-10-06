<?php

namespace App\Filament\Resources\ExpenseReportResource\Pages;

use App\Filament\Resources\ExpenseReportResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenseReports extends ListRecords
{
    protected static string $resource = ExpenseReportResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas'),

            'draft' => Tab::make('Borrador')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder =>
                        $query->where(
                            'status',
                            'draft'
                        )
                ),

            'pending' => Tab::make('Pendiente aprobación')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder =>
                        $query->whereIn(
                            'status',
                            [
                                'submitted',
                                'pending_approval',
                            ]
                        )
                ),

            'approved' => Tab::make('Aprobadas')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder =>
                        $query->where(
                            'status',
                            'approved'
                        )
                ),

            'rejected' => Tab::make('Rechazadas')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder =>
                        $query->where(
                            'status',
                            'rejected'
                        )
                ),

            'paid' => Tab::make('Pagadas')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder =>
                        $query->where(
                            'status',
                            'paid'
                        )
                ),

            'closed' => Tab::make('Cerradas')
                ->icon('heroicon-o-lock-closed')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder =>
                        $query->where(
                            'status',
                            'closed'
                        )
                ),

            'cancelled' => Tab::make('Canceladas')
                ->icon('heroicon-o-no-symbol')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder =>
                        $query->where(
                            'status',
                            'cancelled'
                        )
                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
