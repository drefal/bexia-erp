<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Support\Billing\SwPacClient;
use App\Support\Billing\CsdValidator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_sw_connection')
                ->label('Probar conexión PAC')
                ->icon('heroicon-o-bolt')
                ->color('info')
                ->visible(fn (): bool => CompanyResource::currentUserIsSystemAdmin())
                ->action(function (): void {
                    $result = app(SwPacClient::class)->testAuthentication($this->record->refresh());

                    Notification::make()
                        ->title($result['success'] ? 'Conexión correcta con SW' : 'Error de conexión con SW')
                        ->body($result['message'])
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();

                    $this->record->refresh();
                }),


            Actions\Action::make('validate_csd')
                ->label('Validar CSD')
                ->icon('heroicon-o-document-check')
                ->color('gray')
                ->visible(fn (): bool => CompanyResource::currentUserIsSystemAdmin())
                ->action(function (): void {
                    $result = app(CsdValidator::class)->validate($this->record->refresh());

                    Notification::make()
                        ->title($result['success'] ? 'CSD válido' : 'Error en CSD')
                        ->body($result['message'])
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();

                    $this->record->refresh();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
