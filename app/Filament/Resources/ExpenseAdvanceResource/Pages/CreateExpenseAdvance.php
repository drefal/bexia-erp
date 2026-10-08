<?php

namespace App\Filament\Resources\ExpenseAdvanceResource\Pages;

use App\Filament\Resources\ExpenseAdvanceResource;
use App\Models\ExpenseAdvance;
use App\Support\Expenses\ExpenseAdvanceService;
use App\Support\Expenses\ExpenseAdvanceWorkflowService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateExpenseAdvance extends CreateRecord
{
    protected static string $resource =
        ExpenseAdvanceResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $companyId = (int) (
            Filament::getTenant()?->getKey() ?? 0
        );

        $data['company_id'] = $companyId;

        return app(ExpenseAdvanceService::class)
            ->prepareForCreate($data);
    }

    protected function handleRecordCreation(
        array $data
    ): Model {
        return DB::transaction(
            function () use ($data): ExpenseAdvance {
                /** @var ExpenseAdvance $record */
                $record = ExpenseAdvance::query()
                    ->create($data);

                $record = app(
                    ExpenseAdvanceService::class
                )->assignNumber($record);

                /*
                 * Crear = formalizar y enviar.
                 * Si falla aprobación, toda la creación
                 * hace rollback.
                 */
                return app(
                    ExpenseAdvanceWorkflowService::class
                )->submit(
                    $record,
                    auth()->id()
                );
            }
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl(
            'view',
            ['record' => $this->record]
        );
    }
}
