<?php

/* BEXIA_V5526E_GLOBAL_INVOICE_NO_INTERNAL_ISSUE */

/* BEXIA_V5525K2_HIDE_TECHNICAL_CFDI_ACTIONS */

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Support\Billing\PosGlobalInvoiceService;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/*
 * BEXIA_V5525K_HIDE_TECHNICAL_CFDI_ACTIONS
 * Acciones técnicas CFDI ocultas de la UI operativa.
 * El flujo operativo debe ser Timbrar CFDI, que internamente valida, asigna folio, genera XML y timbra.
 */
class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /*
             * BEXIA_V5526D_CANCEL_GLOBAL_DRAFT_ACTION
             */
            Actions\Action::make('cancel_global_invoice_draft')
                ->label('Cancelar factura global')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar factura global en borrador')
                ->modalDescription('Esto cancelará internamente la factura global en borrador y liberará los tickets para que vuelvan a estar disponibles. No aplica a CFDI timbrados.')
                ->modalSubmitActionLabel('Sí, cancelar y liberar tickets')
                ->modalCancelActionLabel('Salir')
                ->visible(fn (): bool => ! InvoiceResource::isLegacyReadOnly($this->record)
                    && app(PosGlobalInvoiceService::class)->canCancelDraftGlobalInvoice($this->record))
                ->action(function (): void {
                    try {
                        app(PosGlobalInvoiceService::class)
                            ->cancelDraftGlobalInvoice($this->record, auth()->id());

                        $this->record->refresh();

                        \Filament\Notifications\Notification::make()
                            ->title('Factura global cancelada')
                            ->body('Los tickets fueron liberados correctamente.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        report($e);

                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo cancelar la factura global')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),



            /*
             * BEXIA_V5526L_INTERNAL_CANCEL_ONLY_ON_VIEW
             * Las cancelaciones destructivas viven en Ver factura, no en Editar.
             * No aplica a factura global ni a CFDI timbrado.
             */
            Actions\Action::make('cancel_internal_invoice_draft')
                ->label('Cancelar factura interna')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ! InvoiceResource::isLegacyReadOnly($this->record)
                    && (string) ($this->record->source_type ?? '') !== 'pos_global_invoice'
                    && (string) ($this->record->status ?? '') !== 'cancelled'
                    && blank($this->record->cfdi_uuid ?? null)
                    && ! in_array((string) ($this->record->cfdi_status ?? ''), ['stamped', 'cancel_requested', 'cancelled'], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->helperText('Cancelación interna. Solo aplica a facturas sin timbrar.')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Cancelar factura interna')
                ->modalDescription('Esta acción cancela internamente una factura sin timbrar. Para factura global usa “Cancelar factura global”. Para CFDI timbrado usa “Cancelar CFDI”.')
                ->modalSubmitActionLabel('Sí, cancelar factura')
                ->modalCancelActionLabel('Salir')
                ->action(function (array $data): void {
                    InvoiceResource::cancelInvoice($this->record, (string) ($data['reason'] ?? ''));

                    Notification::make()
                        ->title('Factura cancelada')
                        ->body('La factura fue cancelada internamente.')
                        ->success()
                        ->send();

                    $this->record->refresh();

                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $this->record]));
                }),

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
                        ->default(fn () => app(\App\Support\Billing\InvoiceCfdiEmailService::class)->defaultMessage($this->record)),
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

            /* BEXIA_V5523T8_DOWNLOAD_PDF_CACHE_BUST */
            Actions\Action::make('download_cfdi_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => $this->isStamped())
                ->url(fn (): string => route('billing.invoices.download', ['invoice' => $this->record, 'type' => 'pdf']).'?v='.now()->timestamp)
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
                ->visible(fn (): bool => ! InvoiceResource::isLegacyReadOnly($this->record)
                    && $this->isStamped()
                    && ! $this->hasCancelRequestPrepared())
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


            Actions\Action::make('query_cfdi_cancel_status')
                ->label('Consultar cancelación')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->canRefreshCancelStatus())
                ->requiresConfirmation()
                ->modalHeading('Consultar cancelación SAT')
                ->modalDescription('Consulta el estatus oficial del CFDI ante SAT. Si SAT confirma la cancelación, Bexia actualizará la factura a Cancelado CFDI.')
                ->modalSubmitActionLabel('Consultar ahora')
                ->modalCancelActionLabel('Salir')
                ->action(function (): void {
                    $result = app(\App\Support\Billing\InvoiceCfdiCancelService::class)
                        ->refreshCancellationStatus($this->record, auth()->user());

                    Notification::make()
                        ->title(($result['success'] ?? false) ? 'Consulta realizada' : 'No se pudo consultar')
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
                ->visible(fn (): bool => ! InvoiceResource::isLegacyReadOnly($this->record)
                    && ! $this->isFinalCfdiStatus()),
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
        return ! InvoiceResource::isLegacyReadOnly($this->record)
            && $this->isStamped()
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


    private function canRefreshCancelStatus(): bool
    {
        /*
         * BEXIA_V5526V_QUERY_CFDI_CANCEL_STATUS_BUTTON
         */
        return ! InvoiceResource::isLegacyReadOnly($this->record)
            && filled($this->record->cfdi_uuid ?? null)
            && in_array((string) ($this->record->cfdi_cancel_status ?? ''), [
                'cancel_requested',
                'cancel_error',
            ], true);
    }


    private function canStamp(): bool
    {
        if (InvoiceResource::isLegacyReadOnly($this->record)) {
            return false;
        }

        /*
         * BEXIA_V5526N_SHOW_STAMP_FOR_DRAFT_INVOICES
         * El botón Timbrar CFDI debe aparecer también para facturas en borrador
         * sin UUID. La acción ya asigna folio, genera XML y luego timbra.
         */
        if (filled($this->record->cfdi_uuid ?? null)) {
            return false;
        }

        if ((string) ($this->record->status ?? '') === 'cancelled') {
            return false;
        }

        if (in_array((string) ($this->record->cfdi_status ?? ''), [
            'stamped',
            'cancel_requested',
            'cancelled',
            'cancelled_internal',
        ], true)) {
            return false;
        }

        if (in_array((string) ($this->record->cfdi_status ?? ''), [
            'ready_to_stamp',
            'stamp_error',
        ], true)) {
            return true;
        }

        return in_array((string) ($this->record->status ?? ''), [
            'draft',
            'issued',
        ], true) && blank($this->record->cfdi_status ?? null);
    }
}
