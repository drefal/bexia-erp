<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('validate_cfdi')
                ->label('Validar CFDI')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->visible(fn (): bool => ! in_array((string) ($this->record->cfdi_status ?? ''), ['stamped', 'cancelled'], true))
                ->action(function (): void {
                    InvoiceResource::recalculateInvoice($this->record);
                    $this->record->refresh();

                    $result = app(\App\Support\Billing\InvoiceCfdiValidator::class)->validate($this->record, auth()->user());

                    Notification::make()
                        ->title($result['success'] ? 'Factura lista para timbrar' : 'Factura con errores CFDI')
                        ->body($result['message'])
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();

                    $this->record->refresh();

                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\EditAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Factura ' . ($this->record?->number ?: '');
    }
}
