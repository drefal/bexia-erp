<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Models\SaleOrder;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class DeliverSaleOrder extends Page
{
    protected static string $resource = SaleOrderResource::class;

    protected static string $view = 'filament.sales-orders.deliver-sale-order-page';

    public SaleOrder $record;

    public function mount(mixed $record = null, mixed $tenant = null): void
    {
        $this->record = $this->resolveSaleOrderRecord($record, $tenant);

        if (! in_array((string) $this->record->status, ['confirmed', 'partially_delivered'], true)) {
            Notification::make()
                ->title('La orden no está lista para entrega')
                ->body('Solo las órdenes confirmadas o parcialmente entregadas con cantidades pendientes pueden crear entregas.')
                ->warning()
                ->send();

            $this->redirect($this->orderUrl('edit'));

            return;
        }
    }

    public function getTitle(): string
    {
        return 'Entrega ' . ($this->record->number ?: ('#' . $this->record->id));
    }

    public function getHeading(): string
    {
        return 'Entrega de orden de venta';
    }

    public function getSubheading(): ?string
    {
        return ($this->record->number ?: ('Orden #' . $this->record->id))
            . ' · Cliente: '
            . ($this->record->customer_name ?: 'Sin cliente');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('set_quantities')
                ->label('Establecer cantidades')
                ->icon('heroicon-o-check-circle')
                ->color('primary')
                ->url('#')
                ->extraAttributes([
                    'data-bexia-fill-delivery-quantities' => '1',
                    'onclick' => "event.preventDefault(); event.stopPropagation(); if (window.bexiaFillDeliveryQuantities) { window.bexiaFillDeliveryQuantities(); } else { window.dispatchEvent(new CustomEvent('bexia-fill-delivery-quantities')); } return false;",
                ]),

            Actions\Action::make('back_to_order')
                ->label('Volver a la orden')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => $this->orderUrl('edit')),
        ];
    }

    protected function resolveSaleOrderRecord(mixed $record = null, mixed $tenant = null): SaleOrder
    {
        if ($record instanceof SaleOrder) {
            return $record;
        }

        if ($tenant instanceof SaleOrder) {
            return $tenant;
        }

        $recordId = $this->extractRecordId($record);

        if (! $recordId) {
            $recordId = $this->extractRecordId($tenant);
        }

        if (! $recordId) {
            $routeRecord = request()->route('record');

            if ($routeRecord instanceof SaleOrder) {
                return $routeRecord;
            }

            $recordId = $this->extractRecordId($routeRecord);
        }

        if (! $recordId) {
            abort(404, 'No se pudo identificar la orden de venta para entrega.');
        }

        return SaleOrder::query()->findOrFail($recordId);
    }

    protected function extractRecordId(mixed $value): ?int
    {
        if ($value instanceof SaleOrder) {
            return (int) $value->getKey();
        }

        if (is_object($value) && isset($value->id) && is_numeric($value->id)) {
            return (int) $value->id;
        }

        if (is_array($value) && isset($value['id']) && is_numeric($value['id'])) {
            return (int) $value['id'];
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if (ctype_digit($value)) {
                return (int) $value;
            }

            if (str_starts_with($value, '{')) {
                $decoded = json_decode($value, true);

                if (is_array($decoded) && isset($decoded['id']) && is_numeric($decoded['id'])) {
                    return (int) $decoded['id'];
                }
            }
        }

        return null;
    }

    protected function tenantKey(): string|int|null
    {
        $tenant = request()->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getRouteKey')) {
            return $tenant->getRouteKey();
        }

        if ($tenant) {
            return $tenant;
        }

        try {
            $filamentTenant = Filament::getTenant();

            if (is_object($filamentTenant) && method_exists($filamentTenant, 'getRouteKey')) {
                return $filamentTenant->getRouteKey();
            }

            if ($filamentTenant) {
                return $filamentTenant;
            }
        } catch (\Throwable $e) {
            //
        }

        return $this->record->company_id;
    }

    protected function orderUrl(string $page): string
    {
        return route('filament.admin.resources.sale-orders.' . $page, [
            'tenant' => $this->tenantKey(),
            'record' => $this->record->getKey(),
        ]);
    }
}
