<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Support\PurchaseDocumentLinks;
use App\Support\PurchaseOrderApprovalActions;
use App\Support\PurchaseOrderApprovalEngine;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('receive_purchase_order')
                ->label('Recepción')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn (): bool => $this->canReceivePurchaseOrder())
                ->url(fn (): string => route('purchases.orders.receipts.edit', ['purchaseOrder' => $this->record->getKey()])),

            Actions\Action::make('map_xml_lines')
                ->label('Mapear XML')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->visible(fn (): bool => $this->isXmlPurchaseOrder())
                ->url(fn (): string => route('purchases.orders.xml-mapping.edit', ['purchaseOrder' => $this->record->getKey()])),



            Actions\Action::make('duplicate_purchase_order')
                ->label('Duplicar')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                
                
                
                
                    ->visible(fn (): bool => false),

            Actions\Action::make('origin_request')
                ->label(fn (): string => 'Solicitud ' . (PurchaseDocumentLinks::requestNumberForOrder((object) $this->record->toArray()) ?: 'origen'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->visible(fn (): bool => PurchaseDocumentLinks::requestForOrder((object) $this->record->toArray()) !== null)
                ->url(fn (): string => PurchaseDocumentLinks::requestUrlFromOrder((object) $this->record->toArray())),


            Actions\Action::make('stock_transactions')
                ->label('Recepciones de compra')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->visible(fn (): bool => $this->hasInventoryMovements())
                ->url(fn (): string => url('/admin/' . (int) ($this->record->company_id ?? request()->route('tenant')) . '/stock-movements')),

            Actions\Action::make('print_order')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->openUrlInNewTab()
                ->url(fn (): string => url('/purchases/orders/' . $this->record->getKey() . '/print')),

            Actions\Action::make('approve_pending_order')
                ->label('Aprobar OC')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar orden de compra')
                ->modalDescription('La orden será aprobada y quedará confirmada. Se notificará al solicitante.')
                ->visible(fn (): bool => $this->canApproveCurrentOrder())
                ->action(fn (): mixed => $this->approveCurrentOrder()),

            Actions\Action::make('reject_pending_order')
                ->label('Rechazar OC')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Rechazar orden de compra')
                ->modalDescription('La orden regresará a borrador y se notificará el motivo al solicitante.')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo de rechazo')
                        ->required()
                        ->minLength(5)
                        ->rows(4)
                        ->placeholder('Ej. El costo no coincide con la negociación aprobada.'),
                ])
                ->visible(fn (): bool => $this->canApproveCurrentOrder())
                ->action(fn (array $data): mixed => $this->rejectCurrentOrder((string) ($data['reason'] ?? ''))),

            Actions\Action::make('confirm_order')
                ->label('Confirmar orden')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['draft', 'borrador'], true))
                ->url(fn (): string => url('/purchases/orders/' . $this->record->getKey() . '/confirm')),
        ];
    }

    protected function canApproveCurrentOrder(): bool
    {
        if (! in_array((string) ($this->record->status ?? ''), ['review', 'pending_review'], true)) {
            return false;
        }

        return class_exists(PurchaseOrderApprovalActions::class)
            && PurchaseOrderApprovalActions::pendingStepForOrder((int) $this->record->getKey(), (int) auth()->id()) !== null;
    }

    public function approveCurrentOrder(): mixed
    {
        try {
            $message = PurchaseOrderApprovalActions::approve(
                (int) $this->record->getKey(),
                (int) auth()->id()
            );

            Notification::make()
                ->title($message)
                ->success()
                ->send();

            return $this->redirect(
                url('/admin/' . (int) ($this->record->company_id ?? request()->route('tenant')) . '/purchase-orders/' . $this->record->getKey() . '/edit')
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo aprobar la orden')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    public function rejectCurrentOrder(string $reason): mixed
    {
        try {
            $message = PurchaseOrderApprovalActions::reject(
                (int) $this->record->getKey(),
                (int) auth()->id(),
                $reason
            );

            Notification::make()
                ->title($message)
                ->warning()
                ->send();

            return $this->redirect(
                url('/admin/' . (int) ($this->record->company_id ?? request()->route('tenant')) . '/purchase-orders/' . $this->record->getKey() . '/edit')
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo rechazar la orden')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    protected function beforeSave(): void
    {
        if (! in_array((string) ($this->record->status ?? ''), ['draft', 'borrador'], true)) {
            Notification::make()
                ->title('La orden no se puede editar')
                ->body('Solo las órdenes en borrador pueden modificarse.')
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        if (class_exists(PurchaseOrderApprovalEngine::class)) {
            PurchaseOrderApprovalEngine::recalculateLinesAndTotals($this->record);
            $this->record->refresh();
        }

        Notification::make()
            ->title('Orden actualizada')
            ->success()
            ->send();
    }


    protected function isXmlPurchaseOrder(): bool
    {
        if ((bool) ($this->record->created_from_xml ?? false)) {
            return true;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('purchase_order_lines')) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('purchase_order_lines', 'xml_description')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('purchase_order_lines')
            ->where('purchase_order_id', $this->record->getKey())
            ->whereNotNull('xml_description')
            ->exists();
    }

    protected function hasPendingXmlMapping(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('purchase_order_lines')) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('purchase_order_lines', 'xml_requires_mapping')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('purchase_order_lines')
            ->where('purchase_order_id', $this->record->getKey())
            ->where('xml_requires_mapping', true)
            ->count() > 0;
    }


    protected function canReceivePurchaseOrder(): bool
    {
        if (! in_array((string) ($this->record->status ?? ''), ['confirmed', 'partially_received'], true)) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('purchase_order_lines')) {
            return false;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('purchase_order_lines');

        if (! in_array('ordered_quantity', $columns, true)) {
            return false;
        }

        $select = ['id', 'ordered_quantity'];

        if (in_array('received_quantity', $columns, true)) {
            $select[] = 'received_quantity';
        }

        $pending = \Illuminate\Support\Facades\DB::table('purchase_order_lines')
            ->where('purchase_order_id', $this->record->getKey())
            ->get($select)
            ->sum(function ($line): float {
                return max((float) ($line->ordered_quantity ?? 0) - (float) ($line->received_quantity ?? 0), 0);
            });

        return $pending > 0.000001;
    }


    protected function hasInventoryMovements(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('purchase_receipts')) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('purchase_receipts')
            ->where('purchase_order_id', $this->record->getKey())
            ->whereNotNull('stock_movement_id')
            ->exists();
    }


}
