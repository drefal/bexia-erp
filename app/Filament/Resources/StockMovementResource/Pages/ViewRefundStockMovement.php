<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

class ViewRefundStockMovement extends Page
{
    protected static string $resource = StockMovementResource::class;

    protected static string $view = 'filament.stock-movements.view-refund-stock-movement';

    public Model|int|string|null $record = null;

    public function mount($record): void
    {
        $model = static::getResource()::getModel();

        $this->record = $model::query()->findOrFail($record);
    }

    public function getTitle(): string
    {
        return 'Devolución ' . ($this->record->reference ?? '');
    }

    public function getHeading(): string
    {
        return 'Devolución ' . ($this->record->reference ?? '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_stock_movements')
                ->label('Volver a transacciones')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),

            Actions\Action::make('print_pdf')
                ->label('PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => url('/admin/' . (request()->route('tenant') ?? ($this->record->company_id ?? 0)) . '/stock-movements/' . $this->record->id . '/pdf'))
                ->openUrlInNewTab(),
        ];
    }
}
