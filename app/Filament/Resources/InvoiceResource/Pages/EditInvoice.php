<?php

/* BEXIA_V5526L_NO_DESTRUCTIVE_CANCEL_ON_EDIT */

/* BEXIA_V5525K2_HIDE_TECHNICAL_CFDI_ACTIONS */

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Support\Billing\InvoiceCfdiValidator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected bool $customerChangedDuringSave = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->mergeGlobalInvoiceMetadataBeforeSave($data);
        $data = $this->ensureGlobalInvoicePaymentDefaultsBeforeSave($data);

        $oldContactId = (int) ($this->record->contact_id ?? 0);
        $newContactId = (int) ($data['contact_id'] ?? 0);

        if ($newContactId > 0) {
            $newContact = InvoiceResource::contactById($newContactId);

            if ($newContact) {
                $regime = (string) (($newContact->sat_tax_regime_code ?? '') ?: ($newContact->tax_regime ?? ''));
                $selectedUse = (string) ($data['customer_cfdi_use_code'] ?? '');
                $preferredUse = $selectedUse !== ''
                    ? $selectedUse
                    : (string) (($newContact->customer_cfdi_use_code ?? '') ?: ($newContact->sat_cfdi_use_code ?? '') ?: ($newContact->cfdi_use_code ?? ''));

                $data['customer_name'] = InvoiceResource::contactDisplayName($newContact);
                $data['customer_fiscal_name'] = (string) (($newContact->fiscal_name ?? '') ?: ($newContact->name ?? '') ?: ($newContact->commercial_name ?? ''));
                $data['customer_rfc'] = (string) ($newContact->rfc ?? '');
                $data['customer_postal_code'] = (string) (($newContact->fiscal_zip ?? '') ?: ($newContact->fiscal_postal_code ?? '') ?: ($newContact->postal_code ?? ''));
                $data['customer_whatsapp_phone'] = InvoiceResource::contactWhatsappPhone($newContact);
                $data['customer_tax_regime_code'] = $regime;
                $data['customer_cfdi_use_code'] = InvoiceResource::validCfdiUseForRegime($regime, $preferredUse);
                $data['payment_form_code'] = $this->paymentValueForCustomerSnapshot($data, $newContact, 'payment_form_code', ['customer_payment_form_code', 'payment_form_code'], '01');
                $data['payment_method_code'] = $this->paymentValueForCustomerSnapshot($data, $newContact, 'payment_method_code', ['customer_payment_method_code', 'payment_method_code'], 'PUE');
                $data['payment_terms'] = $this->paymentValueForCustomerSnapshot($data, $newContact, 'payment_terms', ['customer_payment_terms_text', 'sales_payment_terms', 'payment_terms'], 'Pago inmediato');
            }
        }

        if ($oldContactId !== $newContactId) {
            $oldContact = $oldContactId > 0 ? InvoiceResource::contactById($oldContactId) : null;
            $newContact = $newContactId > 0 ? InvoiceResource::contactById($newContactId) : null;

            $metadata = $this->record->metadata ?? [];

            if (is_string($metadata)) {
                $decoded = json_decode($metadata, true);
                $metadata = is_array($decoded) ? $decoded : [];
            }

            if (! is_array($metadata)) {
                $metadata = [];
            }

            $metadata['customer_changed_at'] = now()->toDateTimeString();
            $metadata['customer_changed_by_user_id'] = auth()->id();
            $metadata['customer_changed_from_id'] = $oldContactId ?: null;
            $metadata['customer_changed_to_id'] = $newContactId ?: null;
            $metadata['customer_changed_from_label'] = $oldContact ? InvoiceResource::contactDisplayName($oldContact) : 'Sin cliente';
            $metadata['customer_changed_to_label'] = $newContact ? InvoiceResource::contactDisplayName($newContact) : 'Sin cliente';

            $data['metadata'] = $metadata;
            $this->customerChangedDuringSave = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        InvoiceResource::recalculateInvoice($this->record);

        if ($this->customerChangedDuringSave) {
            Notification::make()
                ->title('Cliente de factura actualizado')
                ->body('El cambio quedó registrado. Los datos fiscales se tomaron del cliente seleccionado.')
                ->warning()
                ->send();
        }
    }



    private function paymentValueForCustomerSnapshot(array $data, object $contact, string $field, array $contactFields, string $globalDefault): string
    {
        /*
         * BEXIA_V5526Q_PRESERVE_PAYMENT_FIELDS_ON_CUSTOMER_REFRESH
         * Actualizar cliente no debe borrar forma/metodo/condiciones de pago.
         */
        $submitted = trim((string) ($data[$field] ?? ''));

        if ($submitted !== '') {
            return $submitted;
        }

        return $this->paymentValueFromContactOrCurrent($contact, $field, $contactFields, $globalDefault);
    }

    private function paymentValueFromContactOrCurrent(object $contact, string $invoiceField, array $contactFields, string $globalDefault): string
    {
        foreach ($contactFields as $field) {
            $value = trim((string) ($contact->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        $current = trim((string) ($this->record->{$invoiceField} ?? ''));

        if ($current !== '') {
            return $current;
        }

        if ($this->isGlobalInvoiceForPaymentDefaults()) {
            return $globalDefault;
        }

        return '';
    }

    private function isGlobalInvoiceForPaymentDefaults(): bool
    {
        if ((string) ($this->record->source_type ?? '') === 'pos_global_invoice') {
            return true;
        }

        $metadata = $this->record->metadata ?? [];

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($metadata)) {
            return false;
        }

        return (bool) ($metadata['is_global_invoice'] ?? false)
            || (string) ($metadata['source'] ?? '') === 'pos_global_invoice';
    }



    private function ensureGlobalInvoicePaymentDefaultsBeforeSave(array $data): array
    {
        /*
         * BEXIA_V5526T_GLOBAL_PAYMENT_DEFAULTS_ON_SAVE
         * Una factura global PDV no debe quedar sin forma/metodo/condiciones de pago.
         */
        if (! $this->isGlobalInvoiceForPaymentDefaults()) {
            return $data;
        }

        if (trim((string) ($data['payment_form_code'] ?? '')) === '') {
            $data['payment_form_code'] = trim((string) ($this->record->payment_form_code ?? '')) ?: '01';
        }

        if (trim((string) ($data['payment_method_code'] ?? '')) === '') {
            $data['payment_method_code'] = trim((string) ($this->record->payment_method_code ?? '')) ?: 'PUE';
        }

        if (trim((string) ($data['payment_terms'] ?? '')) === '') {
            $data['payment_terms'] = trim((string) ($this->record->payment_terms ?? '')) ?: 'Pago inmediato';
        }

        return $data;
    }


    private function mergeGlobalInvoiceMetadataBeforeSave(array $data): array
    {
        /*
         * BEXIA_V5526O_SAVE_GLOBAL_INVOICE_INFORMATION_FIELDS
         * Los campos visuales no son columnas; se guardan dentro de metadata.global_invoice.
         */
        $periodicity = trim((string) ($data['global_invoice_periodicity'] ?? ''));
        $month = trim((string) ($data['global_invoice_month'] ?? ''));
        $year = trim((string) ($data['global_invoice_year'] ?? ''));

        unset(
            $data['global_invoice_periodicity'],
            $data['global_invoice_month'],
            $data['global_invoice_year'],
        );

        $isGlobal = (string) ($this->record->source_type ?? $data['source_type'] ?? '') === 'pos_global_invoice';

        if (! $isGlobal) {
            return $data;
        }

        $metadata = $this->record->metadata ?? [];

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $global = $metadata['global_invoice'] ?? [];

        if (! is_array($global)) {
            $global = [];
        }

        $global['periodicity'] = $periodicity !== '' ? $periodicity : (string) ($global['periodicity'] ?? '01');
        $global['month'] = $month !== '' ? str_pad((string) ((int) $month), 2, '0', STR_PAD_LEFT) : (string) ($global['month'] ?? now()->format('m'));
        $global['year'] = $year !== '' ? $year : (string) ($global['year'] ?? now()->format('Y'));

        $metadata['source'] = 'pos_global_invoice';
        $metadata['is_global_invoice'] = true;
        $metadata['global_invoice'] = $global;

        $data['metadata'] = $metadata;

        return $data;
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_customer_snapshot')
                ->label('Actualizar cliente')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => (int) ($this->record->contact_id ?? 0) > 0
                    && ! in_array((string) ($this->record->cfdi_status ?? ''), ['stamped', 'cancelled'], true)
                    && (string) ($this->record->status ?? '') !== 'cancelled')
                ->requiresConfirmation()
                ->modalHeading('Actualizar datos del cliente')
                ->modalDescription('Se volverán a copiar a esta factura los datos fiscales actuales del cliente seleccionado. No cambiará el cliente de la factura.')
                ->action(function (): void {
                    $contactId = (int) ($this->record->contact_id ?? 0);

                    if ($contactId <= 0) {
                        Notification::make()
                            ->title('La factura no tiene cliente seleccionado')
                            ->danger()
                            ->send();

                        return;
                    }

                    $contact = InvoiceResource::contactById($contactId);

                    if (! $contact) {
                        Notification::make()
                            ->title('No se encontró el cliente')
                            ->body('No se pudo actualizar la información fiscal.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $regime = (string) (($contact->sat_tax_regime_code ?? '') ?: ($contact->tax_regime ?? ''));
                    $currentUse = (string) ($this->record->customer_cfdi_use_code ?? '');
                    $contactPreferredUse = (string) (($contact->customer_cfdi_use_code ?? '') ?: ($contact->sat_cfdi_use_code ?? '') ?: ($contact->cfdi_use_code ?? ''));

                    $preferredUse = $currentUse !== ''
                        ? $currentUse
                        : $contactPreferredUse;

                    $metadata = $this->record->metadata ?? [];

                    if (is_string($metadata)) {
                        $decoded = json_decode($metadata, true);
                        $metadata = is_array($decoded) ? $decoded : [];
                    }

                    if (! is_array($metadata)) {
                        $metadata = [];
                    }

                    $metadata['customer_snapshot_refreshed_at'] = now()->toDateTimeString();
                    $metadata['customer_snapshot_refreshed_by_user_id'] = auth()->id();
                    $metadata['customer_snapshot_contact_id'] = $contactId;

                    $this->record->forceFill([
                        'customer_name' => InvoiceResource::contactDisplayName($contact),
                        'customer_fiscal_name' => (string) (($contact->fiscal_name ?? '') ?: ($contact->name ?? '') ?: ($contact->commercial_name ?? '')),
                        'customer_rfc' => (string) ($contact->rfc ?? ''),
                        'customer_postal_code' => (string) (($contact->fiscal_zip ?? '') ?: ($contact->fiscal_postal_code ?? '') ?: ($contact->postal_code ?? '')),
                        'customer_whatsapp_phone' => InvoiceResource::contactWhatsappPhone($contact),
                        'customer_tax_regime_code' => $regime,
                        'customer_cfdi_use_code' => InvoiceResource::validCfdiUseForRegime($regime, $preferredUse),
                        'payment_form_code' => $this->paymentValueFromContactOrCurrent($contact, 'payment_form_code', ['customer_payment_form_code', 'payment_form_code'], '01'),
                        'payment_method_code' => $this->paymentValueFromContactOrCurrent($contact, 'payment_method_code', ['customer_payment_method_code', 'payment_method_code'], 'PUE'),
                        'payment_terms' => $this->paymentValueFromContactOrCurrent($contact, 'payment_terms', ['customer_payment_terms_text', 'sales_payment_terms', 'payment_terms'], 'Pago inmediato'),
                        'metadata' => $metadata,
                    ]);

                    $this->record->save();

                    InvoiceResource::recalculateInvoice($this->record);
                    $this->record->refresh();

                    if (class_exists(\App\Support\Billing\InvoiceCfdiValidator::class)) {
                        app(\App\Support\Billing\InvoiceCfdiValidator::class)->validate($this->record, auth()->user());
                        $this->record->refresh();
                    }

                    Notification::make()
                        ->title('Cliente actualizado en la factura')
                        ->body('Se copiaron nuevamente los datos fiscales actuales del cliente seleccionado.')
                        ->success()
                        ->send();

                    $this->redirect(InvoiceResource::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('download_cfdi_xml')
                ->label('Descargar XML')
                ->icon('heroicon-o-code-bracket-square')
                ->color('success')
                ->visible(fn (): bool => (string) ($this->record->cfdi_status ?? '') === 'stamped' && filled($this->record->cfdi_xml_path ?? null))
                ->url(fn (): string => route('billing.invoices.download', ['invoice' => $this->record, 'type' => 'xml']))
                ->openUrlInNewTab(),

            Actions\Action::make('download_cfdi_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => (string) ($this->record->cfdi_status ?? '') === 'stamped')
                ->url(fn (): string => route('billing.invoices.download', ['invoice' => $this->record, 'type' => 'pdf']))
                ->openUrlInNewTab(),

            Actions\Action::make('download_cfdi_zip')
                ->label('Descargar ZIP')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('warning')
                ->visible(fn (): bool => (string) ($this->record->cfdi_status ?? '') === 'stamped' && filled($this->record->cfdi_xml_path ?? null))
                ->url(fn (): string => route('billing.invoices.download', ['invoice' => $this->record, 'type' => 'zip']))
                ->openUrlInNewTab(),

            Actions\Action::make('stamp_cfdi')
                ->label('Timbrar CFDI')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(fn (): bool => in_array((string) ($this->record->cfdi_status ?? ''), ['ready_to_stamp', 'stamp_error'], true)
                    && ! in_array((string) ($this->record->status ?? ''), ['cancelled'], true))
                ->requiresConfirmation()
                ->modalHeading('Timbrar CFDI con SW')
                ->modalDescription('Se enviará el XML firmado al PAC SW. En DEV se bloqueará si la empresa está configurada contra ambiente producción.')
                ->action(function (): void {
                    InvoiceResource::recalculateInvoice($this->record);
                    $this->record->refresh();

                    $result = app(\App\Support\Billing\InvoiceCfdiStampService::class)
                        ->stamp($this->record, auth()->user());

                    \Filament\Notifications\Notification::make()
                        ->title($result['success'] ? 'CFDI timbrado' : 'No se pudo timbrar')
                        ->body($result['message'])
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();

                    $this->record->refresh();

                    $this->redirect(InvoiceResource::getUrl('edit', ['record' => $this->record]));
                }),

            /* BEXIA_V5526M_HIDE_INTERNAL_ISSUE_FOR_GLOBAL_INVOICE */
            Actions\Action::make('issue_invoice')
                ->label('Facturar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => (string) ($this->record->source_type ?? '') !== 'pos_global_invoice' && (string) ($this->record->status ?? '') === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Marcar factura como facturada')
                ->modalDescription('La factura quedará bloqueada para edición interna. Todavía no timbra CFDI.')
                ->action(function (): void {
                    InvoiceResource::issueInvoice($this->record);
                    $this->redirect(InvoiceResource::getUrl('edit', ['record' => $this->record]));
                }),


            Actions\ViewAction::make(),
        ];
    }


    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        /*
         * BEXIA_V5526M_RENAME_EDIT_CANCEL_ACTION
         * Este botón no cancela la factura; solo sale del formulario sin guardar.
         */
        return parent::getCancelFormAction()
            ->label('Salir sin guardar')
            ->color('gray');
    }


    public function getTitle(): string
    {
        return 'Factura ' . ($this->record?->number ?: '');
    }
}
