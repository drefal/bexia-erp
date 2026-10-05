<?php

namespace App\Filament\Resources\PettyCashFundResource\Pages;

use App\Filament\Resources\PettyCashFundResource;
use App\Models\PettyCashFund;
use App\Support\Expenses\PettyCashFundService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePettyCashFund extends CreateRecord
{
    protected static string $resource = PettyCashFundResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['company_id'] = Filament::getTenant()?->getKey();

        return app(PettyCashFundService::class)->create(
            $data,
            auth()->id()
        );
    }
}
