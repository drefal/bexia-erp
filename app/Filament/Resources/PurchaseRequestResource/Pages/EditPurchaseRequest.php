<?php

namespace App\Filament\Resources\PurchaseRequestResource\Pages;

use App\Filament\Resources\PurchaseRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseRequest extends EditRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function afterSave(): void
    {
        PurchaseRequestResource::recalculateTotals($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('approve_pending_purchase_request')
                ->label('Aprobar SC')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar solicitud de compra')
                ->modalDescription('La solicitud será aprobada. Se notificará al solicitante.')
                ->visible(fn (): bool => $this->canApproveCurrentPurchaseRequest())
                ->url(fn (): string => route('purchases.requests.approve', ['purchaseRequest' => $this->record->getKey()])),


            \Filament\Actions\Action::make('reject_pending_purchase_request')
                ->label('Rechazar SC')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Rechazar solicitud de compra')
                ->modalDescription('La solicitud regresará a borrador y se notificará el motivo al solicitante.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Motivo de rechazo')
                        ->required()
                        ->minLength(5)
                        ->rows(4)
                        ->placeholder('Ej. Falta justificar la compra o el monto no corresponde.'),
                ])
                ->visible(fn (): bool => $this->canApproveCurrentPurchaseRequest())
                ->action(fn (array $data): mixed => $this->rejectCurrentPurchaseRequest((string) ($data['reason'] ?? ''))),


            \Filament\Actions\Action::make('duplicate_purchase_request')
                ->label('Duplicar')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Duplicar solicitud de compra')
                ->modalDescription('Se creará una nueva solicitud en borrador con los mismos productos, cantidades, costos e impuestos.')
                ->url(fn (): string => route('purchases.requests.duplicate', ['purchaseRequest' => $this->record->getKey()])),

            \Filament\Actions\Action::make('related_purchase_order')
                ->label(fn (): string => 'OC ' . (\App\Support\PurchaseDocumentLinks::orderNumberForRequest((int) $this->record->getKey()) ?: 'relacionada'))
                ->icon('heroicon-o-arrow-right')
                ->color('gray')
                ->visible(fn (): bool => \App\Support\PurchaseDocumentLinks::orderForRequest((int) $this->record->getKey()) !== null)
                ->url(fn (): string => route('purchases.requests.open-order', ['purchaseRequest' => $this->record->getKey()])),

            \Filament\Actions\Action::make('create_purchase_order')
                ->label('Crear orden de compra')
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['approved', 'aprobada'], true))
                ->url(fn (): string => route('purchases.requests.create-order', ['purchaseRequest' => $this->record->getKey()])),
            \Filament\Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('purchases.requests.print', ['purchaseRequest' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            Actions\ViewAction::make(),

            Actions\Action::make('sendToReview')
                ->label('Enviar a aprobación')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === 'draft' && PurchaseRequestResource::hasApplicableApprovalWorkflow($this->record))
                ->requiresConfirmation()
                ->action(function (): void {
                    PurchaseRequestResource::recalculateTotals($this->record);
                    $this->record->update(['status' => 'review']);
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'review'], true) && (! PurchaseRequestResource::hasApplicableApprovalWorkflow($this->record) || $this->record->status === 'review'))
                ->requiresConfirmation()
                ->action(function (): void {
                    PurchaseRequestResource::recalculateTotals($this->record);
                    $this->record->update(['status' => 'approved']);
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ! in_array($this->record->status, ['cancelled', 'converted'], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'cancelled']);
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }
    protected function canApproveCurrentPurchaseRequest(): bool
    {
        if (! in_array((string) ($this->record->status ?? ''), ['review', 'pending_review'], true)) {
            return false;
        }

        return class_exists(\App\Support\PurchaseRequestApprovalActions::class)
            && \App\Support\PurchaseRequestApprovalActions::pendingStepForRequest(
                (int) $this->record->getKey(),
                (int) auth()->id()
            ) !== null;
    }

    public function approveCurrentPurchaseRequest(): mixed
    {
        try {
            $message = \App\Support\PurchaseRequestApprovalActions::approve(
                (int) $this->record->getKey(),
                (int) auth()->id()
            );

            \Filament\Notifications\Notification::make()
                ->title($message)
                ->success()
                ->send();

            return $this->redirect(
                url('/admin/' . (int) ($this->record->company_id ?? request()->route('tenant')) . '/purchase-requests/' . $this->record->getKey())
            );
        } catch (\Throwable $e) {
            report($e);

            \Filament\Notifications\Notification::make()
                ->title('No se pudo aprobar la solicitud')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    public function rejectCurrentPurchaseRequest(string $reason): mixed
    {
        try {
            $message = \App\Support\PurchaseRequestApprovalActions::reject(
                (int) $this->record->getKey(),
                (int) auth()->id(),
                $reason
            );

            \Filament\Notifications\Notification::make()
                ->title($message)
                ->warning()
                ->send();

            return $this->redirect(
                url('/admin/' . (int) ($this->record->company_id ?? request()->route('tenant')) . '/purchase-requests/' . $this->record->getKey())
            );
        } catch (\Throwable $e) {
            report($e);

            \Filament\Notifications\Notification::make()
                ->title('No se pudo rechazar la solicitud')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }


}
