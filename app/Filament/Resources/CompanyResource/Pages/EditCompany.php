<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Support\Billing\CsdValidator;
use App\Support\Billing\SwPacClient;
use App\Support\Sat\SatConstanciaCompanyMapper;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_from_sat_constancia')
                ->label('Actualizar desde Constancia SAT')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->visible(fn (): bool => CompanyResource::currentUserIsSystemAdmin())
                ->form([
                    FileUpload::make('constancia_sat')
                        ->label('Constancia de situación fiscal PDF')
                        ->disk('local')
                        ->directory('companies/sat-constancias')
                        ->acceptedFileTypes(['application/pdf'])
                        ->preserveFilenames()
                        ->required()
                        ->helperText('Actualiza RFC, razón social, régimen fiscal, CP y domicilio fiscal desde la constancia.'),
                ])
                ->action(function (array $data): void {
                    $mapper = app(SatConstanciaCompanyMapper::class);
                    $storedPath = $mapper->normalizeStoredPath($data['constancia_sat'] ?? null);

                    if (! $storedPath) {
                        Notification::make()
                            ->title('No se recibió el PDF')
                            ->danger()
                            ->send();

                        return;
                    }

                    $attributes = $mapper->attributesFromStoredPath(
                        $storedPath,
                        $this->record->company_group_id,
                    );

                    if (! $mapper->requiredDataIsPresent($attributes)) {
                        Notification::make()
                            ->title('No se pudo leer toda la información fiscal')
                            ->body('Revisa que el PDF corresponda a una Constancia de Situación Fiscal válida.')
                            ->danger()
                            ->send();

                        return;
                    }

                    unset($attributes['company_group_id'], $attributes['organization_id']);

                    $this->record->fill($attributes);
                    $this->record->slug = $mapper->uniqueSlug(
                        $this->record->business_name ?: $this->record->name,
                        $this->record->getKey(),
                    );
                    $this->record->save();
                    $this->record->refresh();

                    // Muy importante:
                    // La accion actualiza el registro desde un modal de cabecera.
                    // Si no refrescamos el form, Filament conserva valores viejos
                    // y al presionar Guardar cambios puede sobrescribir lo que acabamos de leer del PDF.
                    $this->fillForm();

                    Notification::make()
                        ->title('Empresa actualizada desde Constancia SAT')
                        ->body($this->record->name . ' / RFC: ' . $this->record->tax_id)
                        ->success()
                        ->send();
                }),

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
