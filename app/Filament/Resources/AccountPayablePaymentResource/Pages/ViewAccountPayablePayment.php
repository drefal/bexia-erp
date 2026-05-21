<?php

namespace App\Filament\Resources\AccountPayablePaymentResource\Pages;

use App\Filament\Resources\AccountPayablePaymentResource;
use App\Filament\Resources\AccountPayableResource;
use App\Models\AccountPayablePayment;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewAccountPayablePayment extends ViewRecord
{
    protected static string $resource = AccountPayablePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_payment')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('account-payable-payments.print', [
                    'tenant' => $this->record->company_id,
                    'payment' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Actions\Action::make('cancel_payment')
                ->label('Cancelar pago')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar pago a proveedor')
                ->modalDescription('Esta acción cancelará el pago, liberará la CxP, cancelará el movimiento de tesorería, regresará el saldo a caja/banco y generará la reversa contable si el pago ya tiene póliza.')
                ->modalSubmitActionLabel('Sí, cancelar pago')
                ->visible(fn (): bool => AccountPayablePaymentResource::canCancelPayment($this->record))
                ->action(function (): void {
                    try {
                        /** @var AccountPayablePayment $record */
                        $record = $this->record;

                        $result = AccountPayablePaymentResource::cancelPostedPayment((int) $record->id);

                        $message = 'CxP actualizada a: '
                            . AccountPayableResource::statusLabel($result['payable_status'])
                            . '. Saldo actual: $'
                            . number_format((float) $result['payable_balance'], 2)
                            . '.';

                        if (! empty($result['reversal_entry_id'])) {
                            $message .= ' Reversa contable #' . $result['reversal_entry_id'] . '.';
                        }

                        Notification::make()
                            ->title('Pago cancelado')
                            ->body($message)
                            ->success()
                            ->send();

                        $this->record->refresh();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('No se pudo cancelar el pago')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
