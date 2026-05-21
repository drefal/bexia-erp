<?php

namespace App\Filament\Resources\AccountReceivablePaymentResource\Pages;

use App\Filament\Resources\AccountReceivablePaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

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
        ];
    }
}
