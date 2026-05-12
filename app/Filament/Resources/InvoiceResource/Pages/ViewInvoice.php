<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send_cfdi_email')
                ->label('Enviar por correo')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->visible(fn (): bool => $this->isStamped())
                ->form([
                    TextInput::make('email')
                        ->label('Correo principal')
                        ->email()
                        ->required()
                        ->default(fn () => app(\App\Support\Billing\InvoiceCfdiEmailService::class)->defaultEmail($this->record)),
                    Textarea::make('extra_emails')
                        ->label('Correos adicionales')
                        ->helperText('Opcional. Puedes escribir varios correos separados por coma, punto y coma, espacio o salto de línea.')
                        ->rows(3)
                        ->placeholder('correo1@dominio.com, correo2@dominio.com'),
                    Textarea::make('message')
                        ->label('Mensaje')
                        ->rows(5)
                        ->default(fn () => implode("\n", [
                            'Buen día.',
                            '',
                            'Adjuntamos la factura CFDI '.$this->record->cfdi_series.' '.$this->record->cfdi_folio.'.',
                            '',
                            'UUID: '.$this->record->cfdi_uuid,
                            'Total: $'.number_format((float) ($this->record->total ?? 0), 2),
                            '',
                            'Saludos.',
                        ])),
                ])
                ->modalHeading('Enviar CFDI por correo')
                ->modalSubmitActionLabel('Enviar correo')
                ->action(function (array $data): void {
                    $emails = trim((string) ($data['email'] ?? ''));

                    $extraEmails = trim((string) ($data['extra_emails'] ?? ''));

                    if ($extraEmails !== '') {
                        $emails .= ', '.$extraEmails;
                    }

                    $result = app(\App\Support\Billing\InvoiceCfdiEmailService::class)
                        ->send($this->record, $emails, (string) ($data['message'] ?? ''));

                    Notification::make()
                        ->title(($result['success'] ?? false) ? 'Correo enviado' : 'No se pudo enviar el correo')
                        ->body($result['message'] ?? '')
                        ->color(($result['success'] ?? false) ? 'success' : 'danger')
                        ->send();

                    $this->record->refresh();
                }),

            Actions\Action::make('send_cfdi_whatsapp')
                ->label('Enviar por WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->visible(fn (): bool => $this->isStamped())
                ->requiresConfirmation()
                ->modalHeading('Enviar CFDI por WhatsApp')
                ->modalDescription('El envío por WhatsApp todavía no está desarrollado. Este botón queda preparado para el siguiente módulo.')
                ->modalSubmitActionLabel('Entendido')
                ->action(function (): void {
                    Notification::make()
                        ->title('WhatsApp pendiente')
                        ->body('El envío automático por WhatsApp todavía no está desarrollado. Próximo paso: conectar el proveedor de WhatsApp y adjuntar PDF/XML.')
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('download_cfdi_xml')
                ->label('Descargar XML')
                ->icon('heroicon-o-code-bracket-square')
                ->color('success')
                ->visible(fn (): bool => $this->isStamped() && filled($this->record->cfdi_xml_path ?? null))
                ->url(fn (): string => route('billing.invoices.download', ['invoice' => $this->record, 'type' => 'xml']))
                ->openUrlInNewTab(),

            Actions\Action::make('download_cfdi_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => $this->isStamped())
                ->url(fn (): string => route('billing.invoices.download', ['invoice' => $this->record, 'type' => 'pdf']))
                ->openUrlInNewTab(),

            Actions\Action::make('download_cfdi_zip')
                ->label('Descargar ZIP')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('warning')
                ->visible(fn (): bool => $this->isStamped() && filled($this->record->cfdi_xml_path ?? null))
                ->url(fn (): string => route('billing.invoices.download', ['invoice' => $this->record, 'type' => 'zip']))
                ->openUrlInNewTab(),

            Actions\Action::make('cancel_cfdi')
                ->label('Cancelar CFDI')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->isStamped())
                ->requiresConfirmation()
                ->modalHeading('Cancelar CFDI')
                ->modalDescription('La factura ya está timbrada. La cancelación fiscal SAT requiere motivo de cancelación y, en algunos casos, UUID de sustitución. Este botón queda preparado; el flujo real de cancelación se implementa en el siguiente paso.')
                ->modalSubmitActionLabel('Entendido')
                ->action(function (): void {
                    Notification::make()
                        ->title('Cancelación CFDI pendiente')
                        ->body('El botón ya aparece para facturas timbradas. Falta implementar el flujo fiscal real con motivo SAT y llamada al PAC.')
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('stamp_cfdi_from_app')
                ->label('Timbrar CFDI')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(fn (): bool => $this->canStamp())
                ->requiresConfirmation()
                ->modalHeading('Timbrar CFDI')
                ->modalDescription('Esto emitirá un CFDI real ante el PAC/SAT. Revisa que cliente, totales, serie y folio sean correctos.')
                ->modalSubmitActionLabel('Sí, timbrar CFDI')
                ->action(function (): void {
                    $this->record->refresh();

                    if (blank($this->record->cfdi_series ?? null) || blank($this->record->cfdi_folio ?? null)) {
                        $folio = app(\App\Support\Billing\BillingSeriesResolver::class)
                            ->assignFiscalFolio($this->record, auth()->user());

                        if (! ($folio['success'] ?? false)) {
                            Notification::make()
                                ->title('No se pudo asignar folio')
                                ->body($folio['message'] ?? 'Revisa la serie de facturación.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $this->record->refresh();
                    }

                    $xml = app(\App\Support\Billing\InvoiceCfdiXmlBuilder::class)
                        ->generateSignedXml($this->record, auth()->user());

                    if (! ($xml['success'] ?? false)) {
                        Notification::make()
                            ->title('No se pudo generar XML')
                            ->body($xml['message'] ?? 'Revisa la auditoría CFDI.')
                            ->danger()
                            ->send();

                        $this->record->refresh();

                        return;
                    }

                    $result = app(\App\Support\Billing\InvoiceCfdiStampService::class)
                        ->stamp($this->record->refresh(), auth()->user());

                    Notification::make()
                        ->title(($result['success'] ?? false) ? 'CFDI timbrado' : 'No se pudo timbrar')
                        ->body($result['message'] ?? '')
                        ->color(($result['success'] ?? false) ? 'success' : 'danger')
                        ->send();

                    $this->record->refresh();

                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\EditAction::make()
                ->label('Editar')
                ->visible(fn (): bool => ! $this->isFinalCfdiStatus()),
        ];
    }

    private function isStamped(): bool
    {
        return (string) ($this->record->cfdi_status ?? '') === 'stamped';
    }

    private function isFinalCfdiStatus(): bool
    {
        return in_array((string) ($this->record->cfdi_status ?? ''), [
            'stamped',
            'cancelled',
            'cancelled_internal',
        ], true);
    }

    private function canStamp(): bool
    {
        return in_array((string) ($this->record->cfdi_status ?? ''), [
            'ready_to_stamp',
            'stamp_error',
        ], true) && blank($this->record->cfdi_uuid ?? null);
    }
}
