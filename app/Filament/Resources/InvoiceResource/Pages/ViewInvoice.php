<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                        ->helperText('Opcional. Puedes escribir varios correos separados por coma, punto y coma, espacio o salto de linea.')
                        ->rows(3)
                        ->placeholder('correo1@dominio.com, correo2@dominio.com'),
                    Textarea::make('message')
                        ->label('Mensaje')
                        ->rows(5)
                        ->default(fn () => implode("\n", [
                            'Buen dia.',
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
                ->modalDescription('El envio por WhatsApp todavia no esta desarrollado. Este boton queda preparado para el siguiente modulo.')
                ->modalSubmitActionLabel('Entendido')
                ->action(function (): void {
                    Notification::make()
                        ->title('WhatsApp pendiente')
                        ->body('El envio automatico por WhatsApp todavia no esta desarrollado.')
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
                ->visible(fn (): bool => $this->isStamped() && ! $this->hasCancelRequestPrepared())
                ->form([
                    Select::make('reason_code')
                        ->label('Motivo de cancelación SAT')
                        ->options(fn (): array => app(\App\Support\Billing\InvoiceCfdiCancelService::class)->reasonOptions())
                        ->required()
                        ->validationMessages([
                            'required' => 'Selecciona el motivo de cancelación SAT.',
                        ])
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            if ((string) $state !== '01') {
                                $set('replacement_uuid', null);
                            }
                        }),

                    TextInput::make('replacement_uuid')
                        ->label('UUID sustituto')
                        ->helperText('Obligatorio únicamente cuando el motivo es 01.')
                        ->maxLength(36)
                        ->placeholder('XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX')
                        ->visible(fn (\Filament\Forms\Get $get): bool => (string) $get('reason_code') === '01')
                        ->required(fn (\Filament\Forms\Get $get): bool => (string) $get('reason_code') === '01')
                        ->regex('/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/')
                        ->validationMessages([
                            'required' => 'El motivo 01 requiere UUID sustituto.',
                            'regex' => 'El UUID sustituto no tiene formato válido.',
                        ]),

                    Textarea::make('internal_comment')
                        ->label('Comentario de cancelación')
                        ->helperText('Obligatorio. Explica por qué se prepara la cancelación.')
                        ->rows(4)
                        ->required()
                        ->minLength(8)
                        ->placeholder('Ejemplo: Error en datos fiscales del cliente; se emitirá CFDI sustituto.')
                        ->validationMessages([
                            'required' => 'El comentario de cancelación es obligatorio.',
                            'min' => 'El comentario debe tener al menos 8 caracteres.',
                        ]),
                ])
                ->modalHeading('Cancelar CFDI')
                ->modalDescription('Esta acción registra la solicitud de cancelación en Bexia. Todavía NO cancela ante SAT/PAC; la cancelación fiscal real se enviará en el siguiente paso.')
                ->modalSubmitActionLabel('Registrar solicitud')
                ->modalCancelActionLabel('Salir')
                ->action(function (array $data): void {
                    $result = app(\App\Support\Billing\InvoiceCfdiCancelService::class)
                        ->prepareCancellation($this->record, auth()->user(), $data);

                    if (! ($result['success'] ?? false)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'reason_code' => $result['message'] ?? 'No se pudo preparar la cancelación.',
                        ]);
                    }

                    Notification::make()
                        ->title('Cancelación preparada')
                        ->body($result['message'] ?? 'Cancelación preparada. Pendiente de envío real al PAC/SAT.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            Actions\Action::make('send_cfdi_cancel_to_pac')
                ->label('Enviar cancelación al SAT/PAC')
                ->icon('heroicon-o-paper-airplane')
                ->color('danger')
                ->visible(fn (): bool => $this->canSendCancelToPac())
                ->requiresConfirmation()
                ->modalHeading('Enviar cancelación al SAT/PAC')
                ->modalDescription('Esta acción sí enviará la solicitud real de cancelación al PAC/SAT. Revisa que el motivo SAT, UUID sustituto si aplica y comentario sean correctos.')
                ->modalSubmitActionLabel('Sí, enviar cancelación')
                ->modalCancelActionLabel('Salir')
                ->action(function (): void {
                    $result = app(\App\Support\Billing\InvoiceCfdiCancelService::class)
                        ->sendCancellationToPac($this->record, auth()->user());

                    Notification::make()
                        ->title(($result['success'] ?? false) ? 'Cancelación enviada' : 'No se pudo enviar cancelación')
                        ->body($result['message'] ?? '')
                        ->color(($result['success'] ?? false) ? 'success' : 'danger')
                        ->send();

                    $this->record->refresh();

                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('stamp_cfdi_from_app')
                ->label('Timbrar CFDI')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(fn (): bool => $this->canStamp())
                ->requiresConfirmation()
                ->modalHeading('Timbrar CFDI')
                ->modalDescription('Esto emitira un CFDI real ante el PAC/SAT. Revisa que cliente, totales, serie y folio sean correctos.')
                ->modalSubmitActionLabel('Si, timbrar CFDI')
                ->action(function (): void {
                    $this->record->refresh();

                    if (blank($this->record->cfdi_series ?? null) || blank($this->record->cfdi_folio ?? null)) {
                        $folio = app(\App\Support\Billing\BillingSeriesResolver::class)
                            ->assignFiscalFolio($this->record, auth()->user());

                        if (! ($folio['success'] ?? false)) {
                            Notification::make()
                                ->title('No se pudo asignar folio')
                                ->body($folio['message'] ?? 'Revisa la serie de facturacion.')
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
                            ->body($xml['message'] ?? 'Revisa la auditoria CFDI.')
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
            'cancel_requested',
            'cancelled',
            'cancelled_internal',
        ], true);
    }


    private function canSendCancelToPac(): bool
    {
        return $this->isStamped()
            && (string) ($this->record->cfdi_cancel_status ?? '') === 'ready_to_cancel';
    }

    private function hasCancelRequestPrepared(): bool
    {
        return in_array((string) ($this->record->cfdi_cancel_status ?? ''), [
            'ready_to_cancel',
            'sending_to_pac',
            'cancel_requested',
            'cancelled',
            'cancel_error',
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
