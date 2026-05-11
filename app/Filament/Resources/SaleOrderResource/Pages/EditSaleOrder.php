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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_prices_from_price_list')
                ->label('Actualizar precios')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => \App\Support\SalesPriceListUpdater::shouldShowUpdatePricesButton($this->record))
                ->requiresConfirmation()
                ->modalHeading('Actualizar precios desde lista')
                ->modalDescription('Se actualizarán los precios de las líneas usando la lista de precios del documento. Se recalcularán totales, margen y aprobación.')
                ->action(function (): void {
                    $result = \App\Support\SalesPriceListUpdater::updateFromCurrentPriceList($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Precios actualizados')
                        ->body($result['message'] ?? null)
                        ->success()
                        ->send();

                    $this->redirect(\App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                        'record' => $this->record,
                        'from_tab' => \App\Filament\Resources\SaleOrderResource::listTabForRecord($this->record),
                    ]));
                }),

            Actions\Action::make('request_order_reapproval')
                ->label('Enviar a aprobación')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => \App\Support\SalesApprovalWorkflow::needsOrderReapproval($this->record))
                ->requiresConfirmation()
                ->modalHeading('Enviar orden a aprobación')
                ->modalDescription('La orden fue modificada después de su aprobación. Se enviará nuevamente al flujo configurado.')
                ->action(function (): void {
                    $request = \App\Support\SalesApprovalWorkflow::requestOrderReapproval($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Orden enviada a aprobación')
                        ->body('Solicitud #' . ($request->id ?? ''))
                        ->warning()
                        ->send();

                    $this->redirect(\App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                        'record' => $this->record,
                        'from_tab' => 'por_aprobar',
                    ]));
                }),

            Actions\Action::make('cancel_quote')
                ->label('Cancelar cotización')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'draft'
                    && \App\Filament\Resources\SaleOrderResource::userCanPermission('sales.cancel')
                )
                ->requiresConfirmation()
                ->modalHeading('Cancelar cotización')
                ->modalDescription('La cotización quedará cancelada. No se borrará el historial.')
                ->action(function (): void {
                    \Illuminate\Support\Facades\DB::table('sales_orders')
                        ->where('id', $this->record->id)
                        ->update(\App\Filament\Resources\SaleOrderResource::filterSalesOrderColumns([
                            'status' => 'cancelled',
                            'margin_approval_status' => 'not_required',
                            'margin_approval_required' => false,
                            'margin_approval_requested_at' => null,
                            'updated_at' => now(),
                        ]));

                    if (class_exists(\App\Support\SalesApprovalWorkflow::class)) {
                        $order = \Illuminate\Support\Facades\DB::table('sales_orders')
                            ->where('id', $this->record->id)
                            ->first();

                        if ($order) {
                            \App\Support\SalesApprovalWorkflow::logEvent(
                                $order,
                                'cancelled',
                                'Cotización cancelada',
                                'La cotización fue cancelada.',
                                null,
                                auth()->id()
                            );
                        }
                    }

                    if (\Illuminate\Support\Facades\Schema::hasTable('approval_requests')) {
                        \Illuminate\Support\Facades\DB::table('approval_requests')
                            ->where('approvable_type', \App\Models\SaleOrder::class)
                            ->where('approvable_id', $this->record->id)
                            ->whereIn('document_type', ['sales_quote', 'sales_order', 'sales_margin_approval'])
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'cancelled',
                                'completed_at' => now(),
                                'last_decision_reason' => 'Cotización cancelada.',
                                'updated_at' => now(),
                            ]);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Cotización cancelada')
                        ->success()
                        ->send();

                    $this->redirect(\App\Filament\Resources\SaleOrderResource::listUrlForTab('canceladas'));
                }),

            Actions\Action::make('duplicate_sale_order')
                ->label('Duplicar')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Duplicar como nueva cotización')
                ->modalDescription('Se creará una nueva cotización en borrador con los mismos productos y condiciones. No copiará aprobaciones, entregas, facturas ni pagos.')
                ->action(function (): void {
                    $newOrder = \App\Filament\Resources\SaleOrderResource::duplicateAsQuote($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Cotización duplicada')
                        ->body('Nueva cotización: ' . $newOrder->number)
                        ->success()
                        ->send();

                    $this->redirect(\App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                        'record' => $newOrder,
                        'from_tab' => 'cotizaciones',
                    ]));
                }),

            Actions\Action::make('approve_sales_approval')
                ->label('Aprobar')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => \App\Support\SalesApprovalWorkflow::currentUserCanActOnPendingRequest($this->record))
                ->requiresConfirmation()
                ->modalHeading('Aprobar cotización')
                ->modalDescription('La cotización quedará aprobada y podrá confirmarse como orden de venta.')
                ->action(function (): void {
                    \App\Support\SalesApprovalWorkflow::approvePendingRequestForOrder($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Cotización aprobada')
                        ->success()
                        ->send();

                    $this->redirect(\App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                        'record' => $this->record,
                        'from_tab' => request()->query('from_tab') ?: \App\Filament\Resources\SaleOrderResource::listTabForRecord($this->record),
                    ]));
                }),

            Actions\Action::make('reject_sales_approval')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => \App\Support\SalesApprovalWorkflow::currentUserCanActOnPendingRequest($this->record))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->rows(4),
                ])
                ->modalHeading('Rechazar cotización')
                ->action(function (array $data): void {
                    \App\Support\SalesApprovalWorkflow::rejectPendingRequestForOrder(
                        $this->record,
                        (string) ($data['reason'] ?? '')
                    );

                    \Filament\Notifications\Notification::make()
                        ->title('Cotización rechazada')
                        ->body('Se notificó el motivo al solicitante.')
                        ->warning()
                        ->send();

                    $this->redirect(\App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                        'record' => $this->record,
                        'from_tab' => request()->query('from_tab') ?: \App\Filament\Resources\SaleOrderResource::listTabForRecord($this->record),
                    ]));
                }),





            Actions\Action::make('return_to_quote')
                ->label('Regresar a cotización')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => \App\Support\SalesApprovalWorkflow::canReturnToQuote($this->record))
                ->requiresConfirmation()
                ->modalHeading('Regresar orden a cotización')
                ->modalDescription('Solo se permite si no tiene entregas, facturas ni pagos.')
                ->action(function (): void {
                    \App\Support\SalesApprovalWorkflow::returnToQuote($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Orden regresada a cotización')
                        ->success()
                        ->send();

                    $this->redirect(\App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                        'record' => $this->record,
                        'from_tab' => 'cotizaciones',
                    ]));
                }),

            Actions\Action::make('deliver_order')
                ->label('Entrega')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->button()
                ->visible(fn (): bool => in_array((string) $this->record->status, ['confirmed', 'partially_delivered'], true))
                ->url(function (): string {
                    $tenant = request()->route('tenant');

                    if (is_object($tenant) && method_exists($tenant, 'getRouteKey')) {
                        $tenant = $tenant->getRouteKey();
                    }

                    if (! $tenant) {
                        try {
                            $filamentTenant = \Filament\Facades\Filament::getTenant();

                            if (is_object($filamentTenant) && method_exists($filamentTenant, 'getRouteKey')) {
                                $tenant = $filamentTenant->getRouteKey();
                            } elseif ($filamentTenant) {
                                $tenant = $filamentTenant;
                            }
                        } catch (\Throwable $e) {
                            $tenant = null;
                        }
                    }

                    if (! $tenant) {
                        $tenant = $this->record->company_id;
                    }

                    return url('/admin/' . $tenant . '/sale-orders/' . $this->record->getKey() . '/delivery');
                }),

            Actions\Action::make('view_deliveries')
                ->label('Ver entregas')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->visible(function (): bool {
                    return \Illuminate\Support\Facades\DB::table('sale_deliveries')
                        ->where('sales_order_id', $this->record->getKey())
                        ->exists();
                })
                ->url(function (): string {
                    $tenant = request()->route('tenant');

                    if (is_object($tenant) && method_exists($tenant, 'getRouteKey')) {
                        $tenant = $tenant->getRouteKey();
                    }

                    if (! $tenant) {
                        try {
                            $filamentTenant = \Filament\Facades\Filament::getTenant();

                            if (is_object($filamentTenant) && method_exists($filamentTenant, 'getRouteKey')) {
                                $tenant = $filamentTenant->getRouteKey();
                            } elseif ($filamentTenant) {
                                $tenant = $filamentTenant;
                            }
                        } catch (\Throwable $e) {
                            $tenant = null;
                        }
                    }

                    if (! $tenant) {
                        $tenant = $this->record->company_id;
                    }

                    $search = urlencode((string) ($this->record->number ?: $this->record->getKey()));

                    return url('/admin/' . $tenant . '/sale-deliveries?tableSearch=' . $search);
                }),

            Actions\Action::make('print_pdf')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(
                    fn (): string => route('sales.orders.print', ['saleOrder' => $this->record->getKey()]),
                    shouldOpenInNewTab: true
                ),


            Actions\Action::make('confirm')
                ->label('Confirmar cotización')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'draft' && \App\Filament\Resources\SaleOrderResource::userCanPermission('sales.confirm'))
                ->requiresConfirmation()
                ->modalHeading('Confirmar cotización')
                ->modalDescription('La cotización se convertirá en orden de venta. Este paso todavía no afecta inventario.')
                ->action(function (): void {
                    if (! $this->record->lines()->exists()) {
                        Notification::make()
                            ->title('No se puede confirmar')
                            ->body('La venta debe tener al menos un producto.')
                            ->warning()
                            ->send();

                        return;
                    }

                    if (! \App\Filament\Resources\SaleOrderResource::ensureMarginApprovalBeforeConfirm($this->record)) {
                    return;
                }

                $this->record->confirm();

                    Notification::make()
                        ->title('Cotización convertida en orden de venta')
                        ->success()
                        ->send();
                }),
        ];
    }
    public function getTitle(): string
    {
        $number = $this->record?->number ?: '';

        return $this->record?->status === 'draft'
            ? trim('Cotización ' . $number)
            : trim('Orden de venta ' . $number);
    }


}
