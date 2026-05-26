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
                ->visible(fn (): bool => in_array((string) ($this->record->status ?? ''), ['validated', 'stamped'], true))
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
        ];
    }
}
