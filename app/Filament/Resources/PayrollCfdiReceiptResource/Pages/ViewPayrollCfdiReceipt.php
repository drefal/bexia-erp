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
