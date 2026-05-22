<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSaleOrder extends EditRecord
{
    protected static string $resource = SaleOrderResource::class;

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }
    protected function salesSectionLabel(): string
    {
        $status = (string) ($this->record->status ?? '');

        if (in_array($status, [
            'confirmed',
            'partially_delivered',
            'delivered',
            'invoiced',
            'partially_invoiced',
            'closed',
        ], true)) {
            return 'Órdenes de venta';
        }

        return 'Cotizaciones';
    }

    protected function salesSectionUrl(): string
    {
        return $this->salesSectionLabel() === 'Órdenes de venta'
            ? SaleOrderResource::getUrl('orders')
            : SaleOrderResource::getUrl('quotes');
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->salesSectionUrl() => $this->salesSectionLabel(),
            '#' => (string) ($this->record->number ?? $this->record->getKey()),
        ];
    }

    public function getTitle(): string
    {
        return $this->salesSectionLabel() === 'Órdenes de venta'
            ? 'Editar orden de venta'
            : 'Editar cotización';
    }

    protected function getHeaderActions(): array
    {
        // V5.61.2d: en modo edicion no mostramos acciones operativas.
        // Enviar a PDV, confirmar, imprimir, cancelar y duplicar viven en Ver.
        return [
            Actions\Action::make('save_changes')
                ->label('Guardar cambios')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->action(fn () => $this->save()),
        ];
    }

}
