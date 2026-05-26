<?php

namespace App\Filament\Resources\PayrollCfdiReceiptResource\Pages;

use App\Filament\Resources\PayrollCfdiReceiptResource;
use App\Support\PayrollCfdi\PayrollCfdiStampService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrollCfdiReceipt extends ViewRecord
{
    protected static string $resource = PayrollCfdiReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel_payroll_cfdi')
                ->label('Cancelar CFDI')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar CFDI nómina')
                ->modalDescription('Esto intentará cancelar el CFDI nómina ante el PAC/SAT. En DEV debe quedar bloqueado por seguridad. La cancelación real solo está permitida en PROD.')
                ->modalSubmitActionLabel('Sí, intentar cancelar')
                ->visible(fn (): bool => (string) ($this->record->status ?? '') === 'stamped' && filled($this->record->uuid ?? null))
                ->form([
                    \Filament\Forms\Components\Select::make('reason')
                        ->label('Motivo SAT de cancelación')
                        ->options([
                            '01' => '01 - Comprobante emitido con errores con relación',
                            '02' => '02 - Comprobante emitido con errores sin relación',
                            '03' => '03 - No se llevó a cabo la operación',
                            '04' => '04 - Operación nominativa relacionada en factura global',
                        ])
                        ->default('02')
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('replacement_uuid')
                        ->label('UUID relacionado')
                        ->helperText('Solo aplica normalmente para motivo 01.')
                        ->maxLength(36),
                ])
                ->action(function (array $data): void {
                    $result = app(\App\Support\PayrollCfdi\PayrollCfdiCancelService::class)->cancel(
                        companyId: (int) $this->record->company_id,
                        receiptId: (int) $this->record->id,
                        reason: (string) ($data['reason'] ?? '02'),
                        replacementUuid: filled($data['replacement_uuid'] ?? null) ? (string) $data['replacement_uuid'] : null,
                        userId: auth()->id(),
                    );

                    if (! ($result['success'] ?? false)) {
                        \Filament\Notifications\Notification::make()
                            ->title(($result['blocked'] ?? false) ? 'Cancelación bloqueada' : 'No se pudo cancelar')
                            ->body(collect($result['errors'] ?? [])->take(6)->implode(PHP_EOL) ?: ($result['message'] ?? 'Revisa el detalle del recibo CFDI nómina.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title('CFDI nómina cancelado')
                        ->body('UUID: ' . (($result['summary']['uuid'] ?? null) ?: 'N/D'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('generate_payroll_cfdi_pdf')
                ->label('Generar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['validated', 'stamped', 'internal_only', 'external_stamped', 'cfdi_not_required'], true))
                ->action(function (): void {
                    $result = app(\App\Support\PayrollCfdi\PayrollCfdiReceiptPdfService::class)->generate(
                        companyId: (int) $this->record->company_id,
                        receiptId: (int) $this->record->id,
                        userId: auth()->id(),
                        force: true,
                    );

                    if (! ($result['success'] ?? false)) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo generar PDF')
                            ->body($result['message'] ?? 'Revisa el recibo CFDI nómina.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title('PDF generado')
                        ->body((string) ($result['summary']['pdf_path'] ?? 'PDF listo.'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('view_payroll_cfdi_pdf')
                ->label('Ver PDF')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->pdf_path ?? null))
                ->url(fn (): string => route('payroll-cfdi-receipts.pdf', ['receipt' => $this->record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('stamp_payroll_cfdi')
                ->label('Timbrar CFDI')
                ->icon('heroicon-o-shield-check')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Timbrar CFDI nómina')
                ->modalDescription('Esto intentará timbrar el CFDI nómina ante el PAC/SAT. En DEV debe quedar bloqueado por seguridad. El timbrado real solo está permitido en PROD.')
                ->modalSubmitActionLabel('Sí, intentar timbrar')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['validated', 'error'], true)
                    && blank($this->record->uuid ?? null)
                    && filled($this->record->xml_path ?? null))
                ->action(function (): void {
                    $result = app(PayrollCfdiStampService::class)->stamp(
                        companyId: (int) $this->record->company_id,
                        receiptId: (int) $this->record->id,
                        userId: auth()->id(),
                    );

                    if (! ($result['success'] ?? false)) {
                        Notification::make()
                            ->title(($result['blocked'] ?? false) ? 'Timbrado bloqueado' : 'No se pudo timbrar')
                            ->body(collect($result['errors'] ?? [])->take(6)->implode(PHP_EOL) ?: ($result['message'] ?? 'Revisa el detalle del recibo CFDI nómina.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title('CFDI nómina timbrado')
                        ->body('UUID: ' . (($result['summary']['uuid'] ?? null) ?: 'N/D'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('alternate_mark_internal_only')
                ->label('Recibo interno')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Marcar como recibo interno')
                ->modalDescription('El recibo quedará como control interno sin CFDI fiscal. No se enviará al PAC/SAT.')
                ->modalSubmitActionLabel('Sí, marcar interno')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['draft', 'validated', 'stamp_error', 'error'], true))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Motivo')
                        ->rows(3)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(\App\Support\PayrollCfdi\PayrollCfdiAlternateFlowService::class)->markInternalOnly(
                        companyId: (int) $this->record->company_id,
                        receiptId: (int) $this->record->id,
                        reason: (string) ($data['reason'] ?? ''),
                        userId: auth()->id(),
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Recibo marcado como interno')
                        ->body('No se enviará al PAC/SAT.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('alternate_mark_not_required')
                ->label('CFDI no requerido')
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Marcar CFDI como no requerido')
                ->modalDescription('El recibo quedará fuera de los pendientes fiscales. No se enviará al PAC/SAT.')
                ->modalSubmitActionLabel('Sí, marcar no requerido')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['draft', 'validated', 'stamp_error', 'error'], true))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Motivo')
                        ->rows(3)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(\App\Support\PayrollCfdi\PayrollCfdiAlternateFlowService::class)->markCfdiNotRequired(
                        companyId: (int) $this->record->company_id,
                        receiptId: (int) $this->record->id,
                        reason: (string) ($data['reason'] ?? ''),
                        userId: auth()->id(),
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('CFDI marcado como no requerido')
                        ->body('El recibo ya no aparecerá como pendiente fiscal.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('alternate_external_stamp')
                ->label('Timbrado externo')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Registrar timbrado externo')
                ->modalDescription('Registra un UUID timbrado fuera de Bexia. Esta acción no envía nada al PAC/SAT.')
                ->modalSubmitActionLabel('Registrar externo')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['draft', 'validated', 'stamp_error', 'error'], true) && blank($this->record->uuid ?? null))
                ->form([
                    \Filament\Forms\Components\TextInput::make('uuid')
                        ->label('UUID externo')
                        ->required()
                        ->maxLength(36),
                    \Filament\Forms\Components\TextInput::make('xml_path')
                        ->label('Ruta XML en storage local')
                        ->maxLength(255)
                        ->helperText('Opcional. Si se informa, debe existir en storage/app.'),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    try {
                        app(\App\Support\PayrollCfdi\PayrollCfdiAlternateFlowService::class)->registerExternalStamp(
                            companyId: (int) $this->record->company_id,
                            receiptId: (int) $this->record->id,
                            uuid: (string) ($data['uuid'] ?? ''),
                            xmlPath: filled($data['xml_path'] ?? null) ? (string) $data['xml_path'] : null,
                            notes: filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                            userId: auth()->id(),
                        );

                        $this->record->refresh();

                        Notification::make()
                            ->title('Timbrado externo registrado')
                            ->body('UUID: ' . (string) ($data['uuid'] ?? ''))
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        Notification::make()
                            ->title('No se pudo registrar')
                            ->body(collect($exception->errors())->flatten()->take(5)->implode(PHP_EOL))
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('alternate_revert')
                ->label('Revertir alterno')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Revertir estado alterno')
                ->modalDescription('Regresa el recibo a pendiente/validado CFDI. Para timbrado externo se usará reversa forzada.')
                ->modalSubmitActionLabel('Sí, revertir')
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['internal_only', 'cfdi_not_required', 'external_stamped'], true))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Motivo')
                        ->rows(3)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(\App\Support\PayrollCfdi\PayrollCfdiAlternateFlowService::class)->revertAlternate(
                        companyId: (int) $this->record->company_id,
                        receiptId: (int) $this->record->id,
                        reason: (string) ($data['reason'] ?? ''),
                        force: (string) ($this->record->status ?? '') === 'external_stamped',
                        userId: auth()->id(),
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Estado alterno revertido')
                        ->body('El recibo regresó a flujo CFDI.')
                        ->success()
                        ->send();
                }),

        ];
    }
}
