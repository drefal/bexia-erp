<?php

namespace App\Filament\Resources\PaymentFormResource\Pages;

use App\Filament\Resources\PaymentFormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentForm extends EditRecord
{
    protected static string $resource = PaymentFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
