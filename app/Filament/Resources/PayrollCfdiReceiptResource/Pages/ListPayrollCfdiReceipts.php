<?php

namespace App\Filament\Resources\PayrollCfdiReceiptResource\Pages;

use App\Filament\Resources\PayrollCfdiReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollCfdiReceipts extends ListRecords
{
    protected static string $resource = PayrollCfdiReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('info')
                ->label('Solo borradores CFDI')
                ->disabled()
                ->color('gray'),
        ];
    }
}
