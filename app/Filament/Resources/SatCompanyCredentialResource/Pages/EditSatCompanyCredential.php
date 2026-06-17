<?php

namespace App\Filament\Resources\SatCompanyCredentialResource\Pages;

use App\Filament\Resources\SatCompanyCredentialResource;
use App\Support\FiscalSat\SatCredentialInspector;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSatCompanyCredential extends EditRecord
{
    protected static string $resource = SatCompanyCredentialResource::class;

    protected function afterSave(): void
    {
        $this->validateEfirmaAfterSave();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Configuración SAT guardada';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('validateEfirma')
                ->label('Validar e.firma')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->validateEfirmaAfterSave();
                    $this->refreshFormData([
                        'certificate_serial',
                        'certificate_valid_from',
                        'certificate_valid_to',
                        'credential_status',
                        'is_enabled',
                        'last_verified_at',
                        'last_error_message',
                    ]);
                }),
        ];
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

        $this->record->refresh();

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
