<?php

namespace App\Filament\Resources\ExpenseReportResource\Pages;

use App\Filament\Resources\ExpenseReportResource;
use Throwable;
use Filament\Notifications\Notification;
use App\Support\Expenses\ExpenseReportApprovalWorkflow;
use App\Models\ExpenseReport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpenseReport extends EditRecord
{
    protected static string $resource = ExpenseReportResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? null) !== ExpenseReport::TYPE_PETTY_CASH) {
            $data['petty_cash_fund_id'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('sendToApproval')
                ->label('Enviar a aprobación')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Enviar comprobación a aprobación')
                ->modalDescription(
                    'Bexia validará los gastos y enviará esta comprobación al flujo configurado. Todavía no se descontará el saldo de la caja chica.'
                )
                ->modalSubmitActionLabel('Enviar')
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status === 'draft'
                )
                ->action(function (): void {
                    try {
                        /*
                         * Guardar cualquier cambio pendiente antes
                         * de ejecutar las validaciones.
                         */
                        $this->save();

                        $this->record->refresh();

                        $approval =
                            ExpenseReportApprovalWorkflow::sendToApproval(
                                $this->record
                            );

                        $this->record->refresh();

                        Notification::make()
                            ->title('Comprobación enviada a aprobación')
                            ->body(
                                'Solicitud de aprobación #'
                                . $approval->id
                                . ' creada correctamente.'
                            )
                            ->success()
                            ->send();

                        $this->redirect(
                            static::getResource()::getUrl(
                                'view',
                                [
                                    'record' => $this->record,
                                ]
                            )
                        );
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('No se pudo enviar a aprobación')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

];
    }
}
