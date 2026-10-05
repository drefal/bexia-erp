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
                        (string) $this->record->status
                            === 'draft'
                        && ExpenseReportResource::canEdit(
                            $this->record
                        )
                ),
        ];
    }
}
