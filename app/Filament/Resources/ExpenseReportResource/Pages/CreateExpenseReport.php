<?php

namespace App\Filament\Resources\ExpenseReportResource\Pages;

use App\Filament\Resources\ExpenseReportResource;
use App\Models\ExpenseAdvance;
use App\Models\ExpenseReport;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseReport extends CreateRecord
{
    protected static string $resource = ExpenseReportResource::class;

    protected ?int $advanceId = null;

    public function mount(): void
    {
        parent::mount();

        $advanceId = (int) request()->query(
            'expense_advance_id',
            0
        );

        if ($advanceId <= 0) {
            return;
        }

        $companyId = (int) Filament::getTenant()->getKey();

        $advance = ExpenseAdvance::query()
            ->whereKey($advanceId)
            ->where('company_id', $companyId)
            ->where(
                'status',
                ExpenseAdvance::STATUS_PENDING_RECONCILIATION
            )
            ->firstOrFail();

        $this->advanceId = (int) $advance->id;

        $this->form->fill([
            'expense_advance_id' => $advance->id,
            'type' => ExpenseReport::TYPE_PETTY_CASH,
            'report_date' => now()->toDateString(),
            'petty_cash_fund_id' =>
                $advance->petty_cash_fund_id,
            'employee_id' =>
                $advance->employee_id,
            'purpose' =>
                'Comprobación del anticipo '
                . $advance->number,
            'notes' => null,
            'lines' => [
                [
                    'spent_by_employee_id' =>
                        $advance->employee_id,
                    'expense_date' =>
                        now()->toDateString(),
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'has_receipt' => false,
                ],
            ],
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl(
            'edit',
            [
                'record' => $this->record,
            ]
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = (int) Filament::getTenant()->getKey();
        $data['status'] = 'draft';
        $data['currency_code'] = 'MXN';
        $data['created_by_user_id'] = auth()->id();
        $data['subtotal'] = 0;
        $data['tax_amount'] = 0;
        $data['total_amount'] = 0;

        $advanceId = (int) (
            $data['expense_advance_id']
            ?? 0
        );

        if ($advanceId > 0) {
            $advance = ExpenseAdvance::query()
                ->whereKey($advanceId)
                ->where(
                    'company_id',
                    $data['company_id']
                )
                ->where(
                    'status',
                    ExpenseAdvance::STATUS_PENDING_RECONCILIATION
                )
                ->first();

            if (! $advance) {
                throw new \RuntimeException(
                    'El anticipo seleccionado no es válido '
                    . 'o ya no está pendiente de comprobar.'
                );
            }

            /*
             * Seguridad:
             * no confiamos en valores enviados por navegador.
             */
            $data['type'] =
                ExpenseReport::TYPE_PETTY_CASH;

            $data['petty_cash_fund_id'] =
                $advance->petty_cash_fund_id;

            $data['employee_id'] =
                $advance->employee_id;

            $data['currency_code'] =
                $advance->currency_code ?: 'MXN';

            $data['expense_advance_id'] =
                $advance->id;
        } elseif (
            ($data['type'] ?? null)
            !== ExpenseReport::TYPE_PETTY_CASH
        ) {
            $data['petty_cash_fund_id'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record->fresh();

        if (! $record->number) {
            $record->forceFill([
                'number' => sprintf(
                    'GAS-%s-%06d',
                    $record->report_date?->format('Ymd') ?? now()->format('Ymd'),
                    $record->id
                ),
            ])->save();
        }

        $record->recalculateTotals();
    }
}
