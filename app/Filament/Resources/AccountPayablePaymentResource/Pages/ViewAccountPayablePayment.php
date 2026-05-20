<?php

namespace App\Filament\Resources\AccountPayablePaymentResource\Pages;

use App\Filament\Resources\AccountPayablePaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountPayablePayment extends ViewRecord
{
    protected static string $resource = AccountPayablePaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_payment')
                ->label('Imprimir pago')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('account-payable-payments.print', [
                    'tenant' => $this->record->company_id,
                    'payment' => $this->record->id,
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
