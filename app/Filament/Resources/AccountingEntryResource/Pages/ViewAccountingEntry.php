<?php

namespace App\Filament\Resources\AccountingEntryResource\Pages;

use App\Filament\Resources\AccountingEntryResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountingEntry extends ViewRecord
{
    protected static string $resource = AccountingEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_pdf')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('accounting.entries.print', [
                    'tenant' => Filament::getTenant()?->getKey(),
                    'accountingEntry' => $this->record->getKey(),
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
