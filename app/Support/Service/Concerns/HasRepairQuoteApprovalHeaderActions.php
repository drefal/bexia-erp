<?php

namespace App\Support\Service\Concerns;

use App\Support\Service\RepairQuoteApprovalDecisionService;

trait HasRepairQuoteApprovalHeaderActions
{
    protected function repairQuoteApprovalHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('approve_internal_repair_quote')
                ->label('Aprobar presupuesto')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar presupuesto de reparación')
                ->modalDescription(
                    'Autoriza la etapa interna actual del presupuesto.'
                )
                ->visible(function (): bool {
                    return $this->record
                        && auth()->check()
                        && RepairQuoteApprovalDecisionService::canCurrentUserDecide(
                            $this->record,
                            (int) auth()->id()
                        );
                })
                ->action(function (): void {
                    try {
                        $result = RepairQuoteApprovalDecisionService::approve(
                            $this->record,
                            auth()->user()
                        );

                        $this->record->refresh();

                        \Filament\Notifications\Notification::make()
                            ->title(
                                $result['completed']
                                    ? 'Presupuesto aprobado internamente'
                                    : 'Etapa de aprobación completada'
                            )
                            ->success()
                            ->send();

                        $this->redirect(
                            $this->getResource()::getUrl(
                                'edit',
                                ['record' => $this->record]
                            )
                        );
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo aprobar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            \Filament\Actions\Action::make('reject_internal_repair_quote')
                ->label('Rechazar presupuesto')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Rechazar presupuesto de reparación')
                ->modalSubmitActionLabel('Rechazar presupuesto')
                ->visible(function (): bool {
                    return $this->record
                        && auth()->check()
                        && RepairQuoteApprovalDecisionService::canCurrentUserDecide(
                            $this->record,
                            (int) auth()->id()
                        );
                })
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->minLength(5)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    try {
                        RepairQuoteApprovalDecisionService::reject(
                            $this->record,
                            auth()->user(),
                            (string) ($data['reason'] ?? '')
                        );

                        $this->record->refresh();

                        \Filament\Notifications\Notification::make()
                            ->title('Presupuesto rechazado')
                            ->body(
                                'La reparación regresó a borrador para corregir y reenviar.'
                            )
                            ->warning()
                            ->send();

                        $this->redirect(
                            $this->getResource()::getUrl(
                                'edit',
                                ['record' => $this->record]
                            )
                        );
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo rechazar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
