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
