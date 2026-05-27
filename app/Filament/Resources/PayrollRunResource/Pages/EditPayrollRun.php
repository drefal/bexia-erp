<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Filament\Resources\PayrollRunResource;
use App\Support\PayrollRunCalculator;
use App\Support\PayrollRunApprovalWorkflow;
use App\Support\PayrollRunCloseService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayrollRun extends EditRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        PayrollRunCloseService::ensureCanEdit($this->record);

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
                ->label('Cerrar nómina')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo del cierre')
                        ->required()
                        ->rows(3)
                        ->default('Cierre definitivo de nómina aprobado.'),
                ])
                ->visible(function (): bool {
                    $user = auth()->user();

                    if (! $user || $this->record->status !== 'approved') {
                        return false;
                    }

                    return (bool) ($user->is_system_admin ?? false)
                        || ($user->email ?? null) === 'admin@bexiaerp.com'
                        || $user->can('nomina.prenomina.cerrar')
                        || $user->can('company.update');
                })
                ->action(function (array $data): void {
                    try {
                        PayrollRunCloseService::close($this->record, auth()->id(), (string) ($data['reason'] ?? ''));

                        Notification::make()
                            ->title('Nómina cerrada y bloqueada')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo cerrar la nómina')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'calculated'], true))
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


            Actions\Action::make('setup_payroll_accounting_defaults')
                ->label('Config. contable')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Preparar configuración contable de nómina')
                ->modalDescription('Creará o actualizará cuentas y mapeos contables por defecto para nómina.')
                ->modalSubmitActionLabel('Preparar configuración')
                ->visible(fn (): bool => $this->getResource()::bexiaCanPayrollAccounting())
                ->action(function (): void {
                    app(\App\Support\Accounting\PayrollAccountingPoster::class)->setupDefaultMappings(
                        companyId: (int) $this->record->company_id,
                        userId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Configuración contable lista')
                        ->body('Cuentas y mapeos de nómina preparados.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('preview_payroll_accounting')
                ->label('Resumen contable')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->visible(fn (): bool => $this->getResource()::bexiaCanPayrollAccounting()
                    && in_array((string) $this->record->status, ['closed', 'approved', 'paid'], true))
                ->action(function (): void {
                    try {
                        $summary = app(\App\Support\Accounting\PayrollAccountingPoster::class)->dryRun(
                            companyId: (int) $this->record->company_id,
                            payrollRunId: (int) $this->record->id,
                        );

                        Notification::make()
                            ->title('Resumen contable de nómina')
                            ->body('Debe: $' . number_format((float) $summary['total_debit'], 2) . PHP_EOL
                                . 'Haber: $' . number_format((float) $summary['total_credit'], 2) . PHP_EOL
                                . 'Líneas: ' . count($summary['lines']))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo generar resumen')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('post_payroll_accounting')
                ->label('Generar póliza')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar póliza contable de nómina')
                ->modalDescription('Generará la póliza contable de esta nómina cerrada. No afecta CFDI, PAC ni SAT.')
                ->modalSubmitActionLabel('Sí, generar póliza')
                ->visible(fn (): bool => $this->getResource()::bexiaCanPayrollAccounting()
                    && in_array((string) $this->record->status, ['closed', 'approved', 'paid'], true)
                    && $this->getResource()::payrollAccountingActiveEntryId($this->record) === null)
                ->action(function (): void {
                    try {
                        $entry = app(\App\Support\Accounting\PayrollAccountingPoster::class)->post(
                            companyId: (int) $this->record->company_id,
                            payrollRunId: (int) $this->record->id,
                            userId: auth()->id(),
                        );

                        $this->record->refresh();

                        Notification::make()
                            ->title('Póliza de nómina generada')
                            ->body('Póliza: ' . (string) ($entry->entry_number ?? ('ID ' . $entry->id)))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo generar póliza')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('view_payroll_accounting_entry')
                ->label('Ver póliza')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn (): bool => $this->getResource()::payrollAccountingAnyEntryId($this->record) !== null)
                ->url(fn (): ?string => $this->getResource()::payrollAccountingEntryUrl($this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('reverse_payroll_accounting')
                ->label('Revertir póliza')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revertir póliza contable de nómina')
                ->modalDescription('Generará una póliza inversa y marcará la póliza original como cancelada.')
                ->modalSubmitActionLabel('Sí, revertir póliza')
                ->visible(fn (): bool => $this->getResource()::bexiaCanPayrollAccounting()
                    && $this->getResource()::payrollAccountingActiveEntryId($this->record) !== null)
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Motivo')
                        ->rows(3)
                        ->required()
                        ->default('Reversa contable de nómina.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $entry = app(\App\Support\Accounting\PayrollAccountingPoster::class)->reverse(
                            companyId: (int) $this->record->company_id,
                            payrollRunId: (int) $this->record->id,
                            reason: (string) ($data['reason'] ?? ''),
                            userId: auth()->id(),
                        );

                        $this->record->refresh();

                        Notification::make()
                            ->title('Póliza de nómina reversada')
                            ->body($entry ? ('Reversa: ' . (string) ($entry->entry_number ?? ('ID ' . $entry->id))) : 'No había póliza activa.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo revertir póliza')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
