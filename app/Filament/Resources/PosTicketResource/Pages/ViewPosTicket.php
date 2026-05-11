<?php

namespace App\Filament\Resources\PosTicketResource\Pages;

use App\Filament\Resources\PosTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewPosTicket extends ViewRecord
{
    protected static string $resource = PosTicketResource::class;

    protected static string $view = 'filament.pos-tickets.view-ticket';

    public function getTitle(): string
    {
        return 'Ticket ' . ($this->record->number ?: ('#' . $this->record->id));
    }

    protected function getHeaderActions(): array
    {
        return [

            \Filament\Actions\Action::make('post_refund_inventory_return')
                ->label('Registrar entrada inventario')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => \App\Filament\Resources\PosTicketResource::v5506gShouldShowInventoryReturn($this->record))
                ->requiresConfirmation()
                ->modalHeading('Registrar entrada de inventario')
                ->modalDescription('Se generará el movimiento de entrada para regresar al inventario los productos de esta devolución.')
                ->modalSubmitActionLabel('Registrar entrada')
                ->action(function (): void {
                    $movementId = \App\Filament\Resources\PosTicketResource::v5506gPostRefundInventory($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Inventario actualizado')
                        ->body($movementId > 0 ? ('Entrada de inventario #' . $movementId . ' registrada.') : 'No había líneas inventariables para regresar.')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),


            \Filament\Actions\Action::make('refund_inside_ticket')
                ->label('Devolución')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => (string) ($this->record->status ?? '') === 'paid' && \App\Filament\Resources\PosTicketResource::v5506bCanCreateRefund() && ! \App\Filament\Resources\PosTicketResource::v5509dHasDoneRefund($this->record))
                ->form(function (): array {
                    $lines = \App\Filament\Resources\PosTicketResource::orderLines((int) $this->record->id);

                    $fields = [
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Motivo de devolución')
                            ->placeholder('Ej. Cliente solicita devolución, producto incorrecto, error de cobro...')
                            ->required()
                            ->rows(3),

                        \Filament\Forms\Components\Toggle::make('refund_all')
                            ->label('Devolver todo el ticket')
                            ->helperText('Activa esta opción para devolver el ticket completo. Si la dejas apagada, captura cantidades por producto.')
                            ->default(false)
                            ->live(),

                        \Filament\Forms\Components\Placeholder::make('partial_refund_help')
                            ->label('Cantidades por producto')
                            ->content('Para devolución parcial, captura la cantidad a devolver. Para devolución total, activa “Devolver todo el ticket”.'),
                    ];

                    foreach ($lines as $line) {
                        $qty = (float) ($line->quantity ?? 0);
                        $price = (float) ($line->unit_price ?? 0);
                        $label = trim((string) ($line->product_name ?? ('Producto #' . ($line->product_id ?? $line->id))));

                        $fields[] = \Filament\Forms\Components\TextInput::make('line_' . $line->id)
                            ->label($label . ' | Vendido: ' . number_format($qty, 2) . ' | Precio: $' . number_format($price, 2))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue($qty)
                            ->step('0.000001')
                            ->disabled(fn (\Filament\Forms\Get $get): bool => (bool) $get('refund_all'));
                    }

                    return $fields;
                })
                ->requiresConfirmation()
                ->modalHeading(fn (): string => 'Devolución - ' . ($this->record->number ?: ('#' . $this->record->id)))
                ->modalDescription('Puedes devolver todo el ticket o capturar cantidades específicas para una devolución parcial.')
                ->modalSubmitActionLabel('Registrar devolución')
                ->action(function (array $data): void {
                    $reason = (string) ($data['reason'] ?? '');
                    $refundAll = (bool) ($data['refund_all'] ?? false);

                    unset($data['reason'], $data['refund_all'], $data['partial_refund_help']);

                    if ($refundAll) {
                        $refundId = \App\Filament\Resources\PosTicketResource::v5506bCreateTotalRefund($this->record, $reason);
                        $typeLabel = 'total';
                    } else {
                        $refundId = \App\Filament\Resources\PosTicketResource::v5509bCreatePartialRefund($this->record, $reason, $data);
                        $typeLabel = 'parcial';
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Devolución registrada')
                        ->body('Se registró la devolución ' . $typeLabel . ' #' . $refundId . ' para el ticket ' . ($this->record->number ?: ('#' . $this->record->id)) . '.')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('back_to_tickets')
                ->label('Volver a tickets')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PosTicketResource::getUrl('index')),

            Actions\Action::make('print_pending')
                ->label('Imprimir ticket pendiente')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->visible(fn (): bool => (string) ($this->record->status ?? '') === 'pending_payment')
                ->url(fn (): string => PosTicketResource::pendingPrintUrl($this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('print_receipt')
                ->label('Imprimir ticket pagado')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->visible(fn (): bool => (string) ($this->record->status ?? '') === 'paid')
                ->url(fn (): string => PosTicketResource::receiptPrintUrl($this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('stock_movement')
                ->label('Ver salida')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->visible(fn (): bool => PosTicketResource::stockMovementUrl($this->record) !== '#')
                ->url(fn (): string => PosTicketResource::stockMovementUrl($this->record)),

            Actions\Action::make('generate_internal_invoice')
                ->label('Generar factura interna')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar factura interna')
                ->modalDescription('Esto creará una factura interna en borrador desde este ticket PDV. No timbra CFDI todavía.')
                ->visible(function (): bool {
                    $status = (string) ($this->record->status ?? '');

                    if ($status !== 'paid') {
                        return false;
                    }

                    return ! \Illuminate\Support\Facades\DB::table('invoices')
                        ->where('source_type', 'pos_order')
                        ->where('source_id', (int) $this->record->id)
                        ->exists();
                })
                ->action(function (): void {
                    try {
                        $invoiceId = app(\App\Support\InternalInvoiceBuilder::class)
                            ->createFromPosOrder((int) $this->record->id, auth()->id());

                        $invoice = \Illuminate\Support\Facades\DB::table('invoices')
                            ->where('id', $invoiceId)
                            ->first();

                        $this->record->refresh();

                        \Filament\Notifications\Notification::make()
                            ->title('Factura interna creada')
                            ->body('Folio: ' . ($invoice->number ?? ('#' . $invoiceId)))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo crear la factura interna')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('view_internal_invoice')
                ->label('Ver factura interna')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => \Illuminate\Support\Facades\DB::table('invoices')
                    ->where('source_type', 'pos_order')
                    ->where('source_id', (int) $this->record->id)
                    ->exists())
                ->url(function (): string {
                    $invoiceId = \Illuminate\Support\Facades\DB::table('invoices')
                        ->where('source_type', 'pos_order')
                        ->where('source_id', (int) $this->record->id)
                        ->value('id');

                    return $invoiceId
                        ? \App\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $invoiceId])
                        : '#';
                }),

            Actions\Action::make('request_billing')
                ->label('Enviar a facturación')
                ->icon('heroicon-o-document-arrow-up')
                ->color('primary')
                ->visible(function (): bool {
                    $status = (string) ($this->record->status ?? '');
                    $billing = PosTicketResource::billingStatus($this->record);

                    return $status === 'paid' && ! in_array($billing, ['requested', 'invoiced'], true);
                })
                ->requiresConfirmation()
                ->modalHeading('Enviar ticket a facturación')
                ->modalDescription('Este paso no timbra CFDI todavía. Solo marca el ticket como solicitado para el futuro módulo de facturación.')
                ->action(function (): void {
                    $metadata = PosTicketResource::metadataArray($this->record);
                    $metadata['billing_status'] = 'requested';
                    $metadata['billing_requested_at'] = now()->toDateTimeString();
                    $metadata['billing_requested_by_user_id'] = auth()->id();

                    DB::table('pos_orders')
                        ->where('id', $this->record->id)
                        ->update([
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);

                    $this->record->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title('Ticket enviado a facturación')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('invoice_portal')
                ->label('Portal facturación')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->visible(fn (): bool => (string) ($this->record->status ?? '') === 'paid')
                ->url(fn (): string => PosTicketResource::invoicePortalUrl($this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'order' => $this->record,
            'metadata' => PosTicketResource::metadataArray($this->record),
            'lines' => PosTicketResource::orderLines((int) $this->record->id),
            'payments' => PosTicketResource::orderPayments((int) $this->record->id),
            'movement' => PosTicketResource::stockMovementForOrder($this->record),
            'pendingPrintUrl' => PosTicketResource::pendingPrintUrl($this->record),
            'receiptPrintUrl' => PosTicketResource::receiptPrintUrl($this->record),
            'invoicePortalUrl' => PosTicketResource::invoicePortalUrl($this->record),
            'stockMovementUrl' => PosTicketResource::stockMovementUrl($this->record),
        ];
    }
}
