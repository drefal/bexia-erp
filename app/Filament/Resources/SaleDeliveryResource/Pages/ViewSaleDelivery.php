<?php

namespace App\Filament\Resources\SaleDeliveryResource\Pages;

use App\Filament\Resources\SaleDeliveryResource;
use Filament\Actions;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;

class ViewSaleDelivery extends ViewRecord
{
    protected static string $resource = SaleDeliveryResource::class;

    public function getTitle(): string
    {
        return 'Entrega ' . ($this->record->number ?: ('#' . $this->record->id));
    }

    public function getHeading(): string
    {
        return 'Entrega de venta';
    }

    public function getSubheading(): ?string
    {
        $orderNumber = $this->record->order?->number ?: ('Orden #' . $this->record->sales_order_id);

        return ($this->record->number ?: ('#' . $this->record->id)) . ' · ' . $orderNumber;
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_delivery')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('sales.deliveries.print', ['saleDelivery' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('validate_delivery')
                ->label('Validar entrega')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => (string) $this->record->status === 'draft' && empty($this->record->stock_movement_id))
                ->requiresConfirmation()
                ->modalHeading('Validar entrega')
                ->modalDescription('Se validará la entrega, se generará el movimiento de salida y se descontará inventario. ¿Deseas continuar?')
                ->modalSubmitActionLabel('Validar entrega')
                ->action(function (): void {
                    $beforeStatus = (string) $this->record->status;

                    app(\App\Http\Controllers\SaleDeliveryController::class)
                        ->validateDelivery(request(), $this->record);

                    $this->record->refresh();

                    if ($beforeStatus === 'draft' && (string) $this->record->status === 'done') {
                        Notification::make()
                            ->title('Entrega validada')
                            ->body('Se generó el movimiento de salida y se descontó inventario.')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('No se pudo validar la entrega')
                        ->body('Revisa existencias o intenta validar desde la orden para ver el detalle.')
                        ->danger()
                        ->send();
                }),

            Actions\Action::make('open_order')
                ->label('Abrir orden')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => url('/admin/' . $this->record->company_id . '/sale-orders/' . $this->record->sales_order_id . '/edit')),

            Actions\Action::make('back_to_list')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => SaleDeliveryResource::getUrl('index')),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ViewEntry::make('delivery_content')
                    ->label('')
                    ->view('filament.sales-deliveries.view-content')
                    ->viewData([
                        'record' => $this->record,
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
