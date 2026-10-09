<?php

namespace App\Filament\Resources\ExpenseReportResource\Pages;

use App\Filament\Resources\ExpenseReportResource;
use Throwable;
use Filament\Notifications\Notification;
use App\Support\Expenses\ExpenseReportApprovalWorkflow;
use App\Support\Expenses\ExpenseReportSubmissionValidator;
use App\Models\ExpenseReport;
use Filament\Actions;
use Filament\Actions\StaticAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

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

    protected function approvalFormUuidErrors(): array
    {
        $errors = [];
        $seen = [];

        $lines = $this->data['lines'] ?? [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $uuid = strtolower(
                trim(
                    (string) (
                        $line['cfdi_uuid']
                        ?? ''
                    )
                )
            );

            if ($uuid === '') {
                continue;
            }

            $number = $lineNumber;

            /*
             * También impedir repetir el mismo UUID dentro
             * de dos renglones de esta misma comprobación.
             */
            if (isset($seen[$uuid])) {
                $errors[] =
                    'Gasto '
                    . $number
                    . ': el UUID CFDI '
                    . $uuid
                    . ' está repetido dentro de esta misma comprobación.';

                continue;
            }

            $seen[$uuid] = true;

            /*
             * Si existe en otra comprobación, es duplicado.
             * Excluimos todo el reporte actual para permitir
             * editar una línea conservando su propio UUID.
             */
            $existing =
                \App\Models\ExpenseReportLine::query()
                    ->where(
                        'cfdi_uuid',
                        $uuid
                    )
                    ->where(
                        'expense_report_id',
                        '!=',
                        (int) $this->record->getKey()
                    )
                    ->with('report:id,number')
                    ->first();

            if (! $existing) {
                continue;
            }

            $reportNumber =
                $existing->report?->number
                ?: '#'
                    . $existing->expense_report_id;

            $errors[] =
                'Gasto '
                . $number
                . ': el UUID CFDI '
                . $uuid
                . ' ya fue utilizado en la comprobación '
                . $reportNumber
                . '.';
        }

        return $errors;
    }

    protected function approvalValidationErrors(): array
    {
        $this->record->refresh();

        $errors =
            ExpenseReportSubmissionValidator::validate(
                $this->record
            );

        $errors = array_merge(
            $errors,
            $this->approvalFormUuidErrors()
        );

        return array_values(
            array_unique($errors)
        );
    }

    protected function approvalModalContent(): HtmlString
    {
        $errors = $this->approvalValidationErrors();

        if ($errors === []) {
            return new HtmlString(
                '<div class="text-sm text-gray-600 dark:text-gray-300">'
                . 'Bexia validará los gastos y enviará esta comprobación '
                . 'al flujo configurado.'
                . '</div>'
            );
        }

        $items = collect($errors)
            ->map(
                fn (string $error): string =>
                    '<li>'
                    . e($error)
                    . '</li>'
            )
            ->implode('');

        return new HtmlString(
            '<div class="space-y-3">'
            . '<div class="font-semibold text-danger-600 '
            . 'dark:text-danger-400">'
            . 'Corrige los siguientes puntos antes de enviar:'
            . '</div>'
            . '<ul class="list-disc space-y-2 pl-5 '
            . 'text-sm text-gray-700 dark:text-gray-200">'
            . $items
            . '</ul>'
            . '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [

            Actions\Action::make('sendToApproval')
                ->label('Enviar a aprobación')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(
                    fn (): string =>
                        $this->approvalValidationErrors() === []
                            ? 'Enviar comprobación a aprobación'
                            : 'No se puede enviar a aprobación'
                )
                ->modalContent(
                    fn (): HtmlString =>
                        $this->approvalModalContent()
                )
                ->modalSubmitAction(
                    function (
                        StaticAction $action
                    ): StaticAction|false {
                        if (
                            $this->approvalValidationErrors()
                            !== []
                        ) {
                            return false;
                        }

                        return $action
                            ->label('Enviar')
                            ->color('warning');
                    }
                )
                ->modalCancelActionLabel(
                    fn (): string =>
                        $this->approvalValidationErrors() === []
                            ? 'Cancelar'
                            : 'Cerrar / Corregir'
                )
                ->visible(
                    fn (): bool =>
                        in_array((string) $this->record->status, ['draft', 'rejected'], true)
                        && (
                            auth()->user()?->can('expenses.admin')
                            || auth()->user()?->can('expenses.submit')
                        )
                )
                ->action(function (): void {
                    try {
                        /*
                         * Guardar cualquier cambio pendiente antes
                         * de ejecutar las validaciones.
                         */
                        $this->save();

                        $this->record->refresh();

                        /*
                         * Segunda defensa: aunque la UI del modal sea
                         * manipulada, no continuar si hay validaciones.
                         */
                        ExpenseReportSubmissionValidator::assertCanSubmit(
                            $this->record
                        );

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
