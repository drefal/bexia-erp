<?php

namespace App\Filament\Resources\ExpenseAdvanceResource\Pages;

use App\Filament\Resources\ExpenseAdvanceResource;
use App\Models\ExpenseAdvance;
use App\Support\Expenses\ExpenseAdvanceService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpenseAdvance extends EditRecord
{
    protected static string $resource =
        ExpenseAdvanceResource::class;

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        /** @var ExpenseAdvance $record */
        $record = $this->record;

        app(ExpenseAdvanceService::class)
            ->assertCanCreate(
                (int) $record->company_id,
                (int) $data['employee_id'],
                (int) $record->id
            );

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var ExpenseAdvance $record */
        $record = $this->record;

        app(ExpenseAdvanceService::class)
            ->refreshDueDate($record);

        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Ver'),
        ];
    }
}
