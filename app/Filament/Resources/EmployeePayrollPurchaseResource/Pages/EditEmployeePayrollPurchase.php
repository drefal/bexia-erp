<?php

namespace App\Filament\Resources\EmployeePayrollPurchaseResource\Pages;

use App\Filament\Resources\EmployeePayrollPurchaseResource;
use App\Support\EmployeePayrollPurchaseService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePayrollPurchase extends EditRecord
{
    protected static string $resource = EmployeePayrollPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm')
                ->label('Confirmar compra')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                // BEXIA_V5833G_CONFIRM_PERMISSION
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status === 'draft'
                        && EmployeePayrollPurchaseResource::canCreate()
                )
                ->action(function (): void {
                    EmployeePayrollPurchaseService::confirm($this->record, auth()->id());

                    Notification::make()
                        ->title('Compra confirmada')
                        ->body('Se generó el calendario de cuotas.')
                        ->success()
                        ->send();

                    $this->redirect(EmployeePayrollPurchaseResource::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->visible(fn (): bool => (string) $this->record->status === 'draft'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
