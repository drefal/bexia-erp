<?php

namespace App\Filament\Resources\SatDownloadRequestResource\Pages;

use App\Filament\Resources\SatDownloadRequestResource;
use App\Support\FiscalSat\SatDownloadRequestSubmitter;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSatDownloadRequest extends CreateRecord
{
    protected static string $resource = SatDownloadRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['metadata'] = array_merge($data['metadata'] ?? [], [
            'created_from' => 'filament',
            'created_by_user_id' => auth()->id(),
        ]);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $model = static::getModel();

        $record = new $model();
        $record->forceFill($data);
        $record->save();

        return $record;
    }

    protected function afterCreate(): void
    {
        $result = app(SatDownloadRequestSubmitter::class)->submit($this->record);

        $this->record->refresh();

        if ($result['ok'] ?? false) {
            Notification::make()
                ->success()
                ->title('Solicitud aceptada por SAT')
                ->body('Solicitud: ' . ($result['request_id'] ?? 'sin identificador'))
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title('SAT no aceptó la solicitud')
            ->body($result['message'] ?? 'Error desconocido.')
            ->send();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Solicitud registrada';
    }
}
