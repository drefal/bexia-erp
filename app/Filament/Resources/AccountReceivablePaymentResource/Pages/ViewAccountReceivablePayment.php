<?php

namespace App\Filament\Resources\AccountReceivablePaymentResource\Pages;

use App\Filament\Resources\AccountReceivablePaymentResource;
use App\Filament\Resources\AccountReceivableResource;
use App\Models\AccountReceivablePayment;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewAccountReceivablePayment extends ViewRecord
{
    protected static string $resource = AccountReceivablePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_receivable_payment')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('account-receivable-payments.print', [
                    'tenant' => $this->record->company_id,
                    'payment' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Actions\Action::make('cancel_payment')
                ->label('Cancelar cobro')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar cobro de cliente')
                ->modalDescription('Esta acción cancelará el cobro, regresará el saldo a la CxC, cancelará el movimiento de tesorería, descontará el saldo de caja/banco y generará la reversa contable si el cobro ya tiene póliza.')
                ->modalSubmitActionLabel('Sí, cancelar cobro')
                ->visible(fn (): bool => AccountReceivablePaymentResource::canCancelPayment($this->record))
                ->action(function (): void {
                    try {
                        /** @var AccountReceivablePayment $record */
                        $record = $this->record;

                        $result = AccountReceivablePaymentResource::cancelPostedPayment((int) $record->id);

                        $message = 'CxC actualizada a: '
                            . AccountReceivableResource::statusLabel($result['receivable_status'])
                            . '. Saldo actual: $'
                            . number_format((float) $result['receivable_balance'], 2)
                            . '.';

                        if (! empty($result['reversal_entry_id'])) {
                            $message .= ' Reversa contable #' . $result['reversal_entry_id'] . '.';
                        }

                        Notification::make()
                            ->title('Cobro cancelado')
                            ->body($message)
                            ->success()
                            ->send();

                        $this->record->refresh();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('No se pudo cancelar el cobro')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
