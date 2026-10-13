<?php

namespace App\Filament\Resources\ExpenseReportResource\Pages;

use App\Filament\Resources\ExpenseReportResource;
use App\Support\Expenses\ExpenseReportApprovalWorkflow;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewExpenseReport extends ViewRecord
{
    protected static string $resource =
        ExpenseReportResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('cancelDraftDocument')
                ->label('Cancelar documento')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Cancelar comprobación en borrador')
                ->modalDescription(
                    'El documento se conservará como cancelado. '
                    . 'Esta acción no elimina folios ni movimientos.'
                )
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo de cancelación')
                        ->required()
                        ->maxLength(1000)
                        ->rows(3),
                ])
                ->modalSubmitActionLabel('Confirmar cancelación')
                ->visible(
                    fn (): bool => \App\Support\Expenses\ExpenseDraftCancellationService::canCancel(
                        $this->record
                    )
                )
                ->action(function (array $data): void {{
                    try {{
                        app(
                            \App\Support\Expenses\ExpenseDraftCancellationService::class
                        )->cancel(
                            $this->record,
                            (string) ($data['reason'] ?? '')
                        );

                        $this->record->refresh();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Documento cancelado')
                            ->send();

                        $this->redirect(
                            static::getResource()::getUrl(
                                'view',
                                ['record' => $this->record]
                            )
                        );
                    }} catch (\Throwable $e) {{
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('No se pudo cancelar')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }}
                }}),



            Actions\Action::make('gastos10dTicket')
                ->label('Vale de caja - Comprobación')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(
                    fn (): bool => in_array(
                        (string) $this->record->status,
                        ['approved', 'paid', 'closed'],
                        true
                    )
                )
                ->action(function (): void {
                    $url = route(
                        'expenses.reports.ticket',
                        [
                            'tenant' => $this->record->company_id,
                            'expenseReport' => $this->record->getKey(),
                        ]
                    );

                    $this->js(
                        "window.open("
                        . json_encode($url)
                        . ", '_blank', 'width=420,height=760');"
                    );
                }),


            Actions\Action::make('approveExpense')
                ->label('Aprobar')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(
                    'Aprobar comprobación de gastos'
                )
                ->modalDescription(
                    'Se aprobará la etapa actual. '
                    . 'Todavía no se descontará el saldo de la caja chica.'
                )
                ->modalSubmitActionLabel('Aprobar')
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                            === 'pending_approval'
                        && ExpenseReportApprovalWorkflow::canCurrentUserAct(
                            $this->record
                        )
                )
                ->action(function (): void {
                    try {
                        ExpenseReportApprovalWorkflow::approveFromDocument(
                            $this->record,
                            auth()->user()
                        );

                        $this->record->refresh();

                        Notification::make()
                            ->title(
                                'Comprobación aprobada'
                            )
                            ->success()
                            ->send();

                        $this->redirect(
                            static::getResource()::getUrl(
                                'view',
                                [
                                    'record' =>
                                        $this->record,
                                ]
                            )
                        );
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(
                                'No se pudo aprobar'
                            )
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('rejectExpense')
                ->label('Rechazar')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make(
                        'reason'
                    )
                        ->label(
                            'Motivo del rechazo'
                        )
                        ->required()
                        ->rows(4)
                        ->maxLength(1000),
                ])
                ->modalHeading(
                    'Rechazar comprobación de gastos'
                )
                ->modalSubmitActionLabel('Rechazar')
                ->visible(
                    fn (): bool =>
                        (string) $this->record->status
                            === 'pending_approval'
                        && ExpenseReportApprovalWorkflow::canCurrentUserAct(
                            $this->record
                        )
                )
                ->action(
                    function (array $data): void {
                        try {
                            ExpenseReportApprovalWorkflow::rejectFromDocument(
                                $this->record,
                                auth()->user(),
                                (string) (
                                    $data['reason']
                                    ?? ''
                                )
                            );

                            $this->record->refresh();

                            Notification::make()
                                ->title(
                                    'Comprobación rechazada'
                                )
                                ->success()
                                ->send();

                            $this->redirect(
                                static::getResource()::getUrl(
                                    'view',
                                    [
                                        'record' =>
                                            $this->record,
                                    ]
                                )
                            );
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title(
                                    'No se pudo rechazar'
                                )
                                ->body(
                                    $e->getMessage()
                                )
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }
                ),

            Actions\EditAction::make()
                ->label('Editar comprobación')
                ->visible(
                    fn (): bool =>
                        in_array(
                            (string) $this->record->status,
                            ['draft', 'rejected'],
                            true
                        )
                        && ExpenseReportResource::canEdit(
                            $this->record
                        )
                ),
        ];
    }
}
