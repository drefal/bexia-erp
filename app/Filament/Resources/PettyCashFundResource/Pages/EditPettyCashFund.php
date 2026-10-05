<?php

namespace App\Filament\Resources\PettyCashFundResource\Pages;

use App\Filament\Resources\PettyCashFundResource;
use App\Models\PettyCashFund;
use App\Support\Expenses\PettyCashFundService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPettyCashFund extends EditRecord
{
    protected static string $resource = PettyCashFundResource::class;

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        /** @var PettyCashFund $record */
        return app(PettyCashFundService::class)->update(
            $record,
            $data
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
