<?php

namespace App\Filament\Resources\ExpenseReportResource\Pages;

use App\Filament\Resources\ExpenseReportResource;
use App\Models\ExpenseReport;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseReport extends CreateRecord
{
    protected static string $resource = ExpenseReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = (int) Filament::getTenant()->getKey();
        $data['status'] = 'draft';
        $data['currency_code'] = 'MXN';
        $data['created_by_user_id'] = auth()->id();
        $data['subtotal'] = 0;
        $data['tax_amount'] = 0;
        $data['total_amount'] = 0;

        if (($data['type'] ?? null) !== ExpenseReport::TYPE_PETTY_CASH) {
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
