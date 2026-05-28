<?php

namespace App\Filament\Resources\TreasuryCashTransferRequestResource\Pages;

use App\Filament\Resources\TreasuryCashTransferRequestResource;
use App\Models\TreasuryCashTransferRequest;
use App\Support\Treasury\CashTransferService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTreasuryCashTransferRequest extends CreateRecord
{
    protected static string $resource = TreasuryCashTransferRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['company_id'] = TreasuryCashTransferRequestResource::currentCompanyId();
        $data['status'] = 'requested';
        $data['requested_by_user_id'] = auth()->id();
        $data['requested_at'] = now();

        $request = app(CashTransferService::class)->createRequest($data);

        return TreasuryCashTransferRequest::query()->findOrFail($request->id);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
