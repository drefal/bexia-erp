<?php

namespace App\Filament\Resources\ExpenseAdvanceResource\Pages;

use App\Filament\Resources\ExpenseAdvanceResource;
use App\Models\ExpenseAdvance;
use App\Support\Expenses\ExpenseAdvanceWorkflowService;
use App\Support\Expenses\ExpenseAdvanceReconciliationService;
use App\Support\Expenses\ExpenseAdvanceReturnService;
use App\Support\Expenses\PettyCashTransferService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewExpenseAdvance extends ViewRecord
{
    protected static string $resource =
        ExpenseAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_reimbursement_receipt')
                ->label('Imprimir vale de reembolso')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function (): void {
                    $url = route(
                        'expenses.advances.reimbursement.print',
                        [
                            'tenant' =>
                                $this->record->company_id,

                            'expenseAdvance' =>
                                $this->record->id,
                        ]
                    );

                    $this->js(
                        "window.open("
                        . json_encode($url)
                        . ", '_blank', 'width=420,height=760');"
                    );
                })
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                            === ExpenseAdvance::STATUS_CLOSED
                        && filled(
                            $this->record
                                ->reimbursement_treasury_movement_id
                        )
                        && filled(
                            $this->record
                                ->reimbursed_at
                        )
                ),

            Actions\Action::make('request_reimbursement')
                ->label(
                    fn (): string =>
                        'Solicitar reembolso · $'
                        . number_format(
                            (float) $this->record
                                ->reimbursement_due_amount,
                            2
                        )
                )
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(
                    'Solicitar reembolso al empleado'
                )
                ->modalDescription(
                    fn (): string =>
                        'Se enviará a aprobación un reembolso de $'
                        . number_format(
                            (float) $this->record
                                ->reimbursement_due_amount,
                            2
                        )
                        . ' desde '
                        . (
                            $this->record
                                ->pettyCashFund
                                ?->number
                            ?: 'la caja chica'
                        )
                        . '. El dinero no saldrá hasta '
                        . 'que la solicitud sea aprobada.'
                )
                ->modalSubmitActionLabel(
                    'Enviar a aprobación'
                )
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                        === ExpenseAdvance::
                            STATUS_PENDING_REIMBURSEMENT
                        && (float) $this->record
                            ->reimbursement_due_amount > 0
                        && ! $this->record
                            ->reimbursement_transfer_request_id
                )
                ->action(function (): void {
                    try {
                        $amount = (float)
                            $this->record
                                ->reimbursement_due_amount;

                        $request = app(
                            PettyCashTransferService::class
                        )->requestAdvanceReimbursement(
                            $this->record,
                            auth()->id()
                        );

                        $this->record->refresh();

                        Notification::make()
                            ->success()
                            ->title(
                                'Reembolso enviado a aprobación'
                            )
                            ->body(
                                'Se solicitó el reembolso de $'
                                . number_format(
                                    $amount,
                                    2
                                )
                                . '. Solicitud '
                                . (
                                    $request->number
                                    ?: ('#' . $request->id)
                                )
                                . '.'
                            )
                            ->send();

                        $this->redirect(
                            ExpenseAdvanceResource::getUrl(
                                'view',
                                [
                                    'record' =>
                                        $this->record,
                                ]
                            )
                        );
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title(
                                'No se pudo solicitar '
                                . 'el reembolso'
                            )
                            ->body(
                                $e->getMessage()
                            )
                            ->send();
                    }
                }),

            Actions\Action::make('print_return_receipt')
                ->label('Imprimir ticket devolución')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function (): void {
                    $url = route(
                        'expenses.advances.return.print',
                        [
                            'tenant' =>
                                $this->record->company_id,

                            'expenseAdvance' =>
                                $this->record->id,
                        ]
                    );

                    $this->js(
                        "window.open("
                        . json_encode($url)
                        . ", '_blank', 'width=420,height=760');"
                    );
                })
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                            === ExpenseAdvance::STATUS_CLOSED
                        && filled(
                            $this->record
                                ->return_treasury_movement_id
                        )
                        && filled(
                            $this->record->returned_at
                        )
                ),

            Actions\Action::make('register_return')
                ->label(
                    fn (): string =>
                        'Registrar devolución · $'
                        . number_format(
                            (float) $this->record
                                ->return_due_amount,
                            2
                        )
                )
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(
                    'Registrar devolución del empleado'
                )
                ->modalDescription(
                    fn (): string =>
                        'Confirma que el empleado devolvió $'
                        . number_format(
                            (float) $this->record
                                ->return_due_amount,
                            2
                        )
                        . ' en efectivo a '
                        . (
                            $this->record
                                ->pettyCashFund
                                ?->number
                            ?: 'la misma caja chica'
                        )
                        . '. Esta operación registrará '
                        . 'la entrada real de efectivo '
                        . 'y cerrará el anticipo.'
                )
                ->modalSubmitActionLabel(
                    'Registrar devolución'
                )
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                        === ExpenseAdvance::
                            STATUS_PENDING_RETURN
                        && (float) $this->record
                            ->return_due_amount > 0
                )
                ->action(function (): void {
                    try {
                        $amount = (float)
                            $this->record
                                ->return_due_amount;

                        $this->record = app(
                            ExpenseAdvanceReturnService::class
                        )->register(
                            $this->record,
                            auth()->id()
                        );

                        Notification::make()
                            ->success()
                            ->title(
                                'Devolución registrada'
                            )
                            ->body(
                                'Ingresaron $'
                                . number_format(
                                    $amount,
                                    2
                                )
                                . ' a la caja chica. '
                                . 'El anticipo quedó cerrado.'
                            )
                            ->send();

                        $this->redirect(
                            ExpenseAdvanceResource::getUrl(
                                'view',
                                [
                                    'record' =>
                                        $this->record,
                                ]
                            )
                        );
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title(
                                'No se pudo registrar '
                                . 'la devolución'
                            )
                            ->body(
                                $e->getMessage()
                            )
                            ->send();
                    }
                }),

            Actions\Action::make('finalize_reconciliation')
                ->label('Finalizar comprobación')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(
                    'Finalizar comprobación del anticipo'
                )
                ->modalDescription(
                    fn (): string =>
                        app(
                            ExpenseAdvanceReconciliationService::class
                        )->resultDescription(
                            $this->record
                        )
                )
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                        === ExpenseAdvance::
                            STATUS_PENDING_RECONCILIATION
                )
                ->action(function (): void {
                    try {
                        $this->record = app(
                            ExpenseAdvanceReconciliationService::class
                        )->finalize(
                            $this->record,
                            auth()->id()
                        );

                        Notification::make()
                            ->success()
                            ->title(
                                'Comprobación finalizada'
                            )
                            ->body(
                                match (
                                    (string) $this->record->status
                                ) {
                                    ExpenseAdvance::
                                        STATUS_CLOSED =>
                                            'El anticipo quedó cuadrado y cerrado.',

                                    ExpenseAdvance::
                                        STATUS_PENDING_RETURN =>
                                            'Quedó pendiente la devolución de $'
                                            . number_format(
                                                (float) $this->record
                                                    ->return_due_amount,
                                                2
                                            )
                                            . '.',

                                    ExpenseAdvance::
                                        STATUS_PENDING_REIMBURSEMENT =>
                                            'Quedó pendiente reembolsar $'
                                            . number_format(
                                                (float) $this->record
                                                    ->reimbursement_due_amount,
                                                2
                                            )
                                            . ' al empleado.',

                                    default =>
                                        'La conciliación fue actualizada.',
                                }
                            )
                            ->send();

                        $this->redirect(
                            ExpenseAdvanceResource::getUrl(
                                'view',
                                [
                                    'record' =>
                                        $this->record,
                                ]
                            )
                        );
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title(
                                'No se pudo finalizar'
                            )
                            ->body(
                                $e->getMessage()
                            )
                            ->send();
                    }
                }),

            Actions\Action::make('create_reconciliation')
                ->label('Comprobar anticipo')
                ->icon('heroicon-o-receipt-percent')
                ->color('primary')
                ->url(
                    fn (): string =>
                        \App\Filament\Resources\ExpenseReportResource::
                            getUrl(
                                'create',
                                [
                                    'expense_advance_id' =>
                                        $this->record->id,
                                ],
                                panel: 'admin',
                                tenant:
                                    $this->record->company
                            )
                )
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                        === ExpenseAdvance::
                            STATUS_PENDING_RECONCILIATION
                ),

            Actions\Action::make('print_voucher')
                ->label('Imprimir vale')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function (): void {
                    $url = route(
                        'expenses.advances.print',
                        [
                            'tenant' =>
                                $this->record->company_id,
                            'expenseAdvance' =>
                                $this->record->id,
                        ]
                    );

                    $this->js(
                        "window.open("
                        . json_encode($url)
                        . ", '_blank', 'width=420,height=760');"
                    );
                })
                ->visible(
                    fn (): bool =>
                        in_array(
                            (string) $this->record->status,
                            [
                                ExpenseAdvance::STATUS_PENDING_RECONCILIATION,
                                ExpenseAdvance::STATUS_PENDING_RETURN,
                                ExpenseAdvance::STATUS_PENDING_REIMBURSEMENT,
                                ExpenseAdvance::STATUS_CLOSED,
                            ],
                            true
                        )
                ),

            Actions\Action::make('submit')
                ->label('Enviar a aprobación')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(
                    'Enviar anticipo a aprobación'
                )
                ->modalDescription(
                    'Después de enviarlo ya no podrá editarse. '
                    . 'El dinero no saldrá de la caja hasta que sea aprobado.'
                )
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                        === ExpenseAdvance::STATUS_DRAFT
                )
                ->action(function (): void {
                    $this->record = app(
                        ExpenseAdvanceWorkflowService::class
                    )->submit(
                        $this->record,
                        auth()->id()
                    );

                    Notification::make()
                        ->success()
                        ->title(
                            'Anticipo enviado a aprobación'
                        )
                        ->body(
                            'El anticipo quedó pendiente de aprobación.'
                        )
                        ->send();

                    $this->redirect(
                        ExpenseAdvanceResource::getUrl(
                            'view',
                            ['record' => $this->record]
                        )
                    );
                }),
        ];
    }
}
