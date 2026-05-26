<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use App\Support\PayrollRunCalculator;
use App\Support\PayrollRunApprovalWorkflow;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayrollRun extends EditRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calculate')
                ->label('Calcular pre-nómina')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'calculated'], true))
                ->action(function (): void {
                    try {
                        PayrollRunCalculator::calculate($this->record, auth()->id());

                        Notification::make()
                            ->title('Pre-nómina calculada')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo calcular')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn (): bool => $this->record->lines()->exists())
                ->action(fn () => $this->getResource()::exportExcel($this->record)),

            Actions\Action::make('export_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => $this->record->lines()->exists())
                ->action(fn () => $this->getResource()::exportPdf($this->record)),

            Actions\Action::make('request_approval')
                ->label('Solicitar aprobación')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'calculated' && auth()->user()?->can('nomina.prenomina.solicitar_aprobacion'))
                ->action(function (): void {
                    try {
                        PayrollRunApprovalWorkflow::sendToApproval($this->record, auth()->id());

                        Notification::make()
                            ->title('Pre-nómina enviada a aprobación')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo solicitar aprobación')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('approve_pending_approval')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => PayrollRunApprovalWorkflow::currentUserCanActOnPendingRequest($this->record))
                ->action(function (): void {
                    try {
                        PayrollRunApprovalWorkflow::approvePendingRequestForRun($this->record, auth()->id());

                        Notification::make()
                            ->title('Pre-nómina aprobada')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo aprobar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('reject_pending_approval')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn (): bool => PayrollRunApprovalWorkflow::currentUserCanActOnPendingRequest($this->record))
                ->action(function (array $data): void {
                    try {
                        PayrollRunApprovalWorkflow::rejectPendingRequestForRun($this->record, auth()->id(), (string) ($data['reason'] ?? ''));

                        Notification::make()
                            ->title('Pre-nómina rechazada')
                            ->warning()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo rechazar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('close')
                ->label('Cerrar')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'approved')
                ->action(function (): void {
                    $this->record->forceFill([
                        'status' => 'closed',
                        'updated_by_user_id' => auth()->id(),
                    ])->save();

                    Notification::make()
                        ->title('Pre-nómina cerrada')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! in_array($this->record->status, ['closed', 'cancelled'], true))
                ->action(function (): void {
                    $this->record->forceFill([
                        'status' => 'cancelled',
                        'updated_by_user_id' => auth()->id(),
                    ])->save();

                    Notification::make()
                        ->title('Pre-nómina cancelada')
                        ->warning()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
