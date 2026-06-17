<?php

namespace App\Filament\Resources\SatCompanyCredentialResource\Pages;

use App\Filament\Resources\SatCompanyCredentialResource;
use App\Support\FiscalSat\SatCredentialInspector;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSatCompanyCredential extends CreateRecord
{
    protected static string $resource = SatCompanyCredentialResource::class;

    protected function afterCreate(): void
    {
        $this->validateEfirmaAfterSave();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Configuración SAT guardada';
    }

    protected function validateEfirmaAfterSave(): void
    {
        $record = $this->record;

        if (! $record->cer_file_path || ! $record->key_file_path || ! $record->password_encrypted) {
            Notification::make()
                ->warning()
                ->title('Configuración SAT guardada')
                ->body('Falta cargar .cer, .key o contraseña para validar la e.firma.')
                ->send();

            return;
        }

        $result = app(SatCredentialInspector::class)->inspectAndUpdate($record);

        if ($result['ok'] ?? false) {
            Notification::make()
                ->success()
                ->title('e.firma validada')
                ->body('Certificado, llave privada y contraseña validados correctamente. La configuración quedó activa para descarga SAT.')
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title('No se pudo validar la e.firma')
            ->body($result['message'] ?? 'Error desconocido.')
            ->send();
    }
}
