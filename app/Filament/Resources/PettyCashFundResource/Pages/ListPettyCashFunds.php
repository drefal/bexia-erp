<?php

namespace App\Filament\Resources\PettyCashFundResource\Pages;

use App\Filament\Resources\PettyCashFundResource;
use App\Filament\Pages\CashCounter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPettyCashFunds extends ListRecords
{
    protected static string $resource = PettyCashFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cashCounter')
                ->label('Contador de efectivo')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->url(
                    fn (): string => CashCounter::getUrl()
                )
                ->openUrlInNewTab(),

            Actions\CreateAction::make()
                ->label('Nueva caja chica'),
        ];
    }
}
