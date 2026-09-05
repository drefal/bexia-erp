<?php

namespace App\Filament\Resources\AttendanceTerminalResource\Pages;

use App\Filament\Resources\AttendanceTerminalResource;
use App\Support\Attendance\AttendanceTerminalPairingService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceTerminal extends EditRecord
{
    protected static string $resource = AttendanceTerminalResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AttendanceTerminalResource::prepareDataForPersistence(
            $data,
            $this->record,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pairTablet')
                ->label('Vincular tablet')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->modalHeading('Vincular esta terminal con una tablet')
                ->modalDescription('Abre /asistencia/kiosco en la tablet y captura aqui el codigo de 6 digitos que aparezca.')
                ->modalSubmitActionLabel('Vincular')
                ->form([
                    TextInput::make('pairing_code')
                        ->label('Codigo de la tablet')
                        ->required()
                        ->minLength(6)
                        ->maxLength(6)
                        ->rules(['regex:/^\d{6}$/'])
                        ->placeholder('000000')
                        ->helperText('El codigo dura 10 minutos y solo puede vincular una terminal.'),
                ])
                ->action(function (array $data): void {
                    app(AttendanceTerminalPairingService::class)
                        ->completePairing(
                            $this->record,
                            (string) $data['pairing_code'],
                        );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Tablet vinculada')
                        ->body('El codigo fue aceptado. La tablet recibira su UUID y token automaticamente.')
                        ->success()
                        ->send();
                }),

            Action::make('blockTerminal')
                ->label('Bloquear')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => ! $this->record->isBlocked())
                ->modalHeading('Bloquear terminal')
                ->modalDescription('La tablet dejara de ser aceptada inmediatamente. El token no se elimina y podra volver a funcionar si se desbloquea.')
                ->modalSubmitActionLabel('Bloquear')
                ->form([
                    Textarea::make('blocked_reason')
                        ->label('Motivo')
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $this->record->forceFill([
                        'blocked_at' => now(),
                        'blocked_reason' => trim((string) $data['blocked_reason']),
                    ])->save();

                    $this->record->refresh();

                    Notification::make()
                        ->title('Terminal bloqueada')
                        ->warning()
                        ->send();
                }),

            Action::make('unblockTerminal')
                ->label('Desbloquear')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn (): bool => $this->record->isBlocked())
                ->requiresConfirmation()
                ->modalHeading('Desbloquear terminal')
                ->modalDescription('La tablet podra volver a autenticarse con el token que ya tiene guardado.')
                ->action(function (): void {
                    $this->record->forceFill([
                        'blocked_at' => null,
                        'blocked_reason' => null,
                    ])->save();

                    $this->record->refresh();

                    Notification::make()
                        ->title('Terminal desbloqueada')
                        ->success()
                        ->send();
                }),

            Action::make('back')
                ->label('Regresar')
                ->url(
                    static::getResource()::getUrl('index')
                ),
        ];
    }
}
