<?php

namespace App\Filament\Resources\SatDownloadRequestResource\Pages;

use App\Filament\Resources\SatDownloadRequestResource;
use App\Support\FiscalSat\SatDownloadPackageDownloader;
use App\Support\FiscalSat\SatDownloadRequestVerifier;
use App\Support\FiscalSat\SatMetadataPackageImporter;
use App\Support\FiscalSat\SatXmlPackageImporter;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSatDownloadRequest extends ViewRecord
{
    protected static string $resource = SatDownloadRequestResource::class;

    public function getTitle(): string
    {
        return 'Solicitud de descarga SAT';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verifySatStatus')
                ->label('Verificar estatus SAT')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => filled($this->record->request_uuid)
                    && in_array($this->record->status, ['requested', 'processing'], true))
                ->action(function (): void {
                    $result = app(SatDownloadRequestVerifier::class)->verify($this->record);

                    $this->record->refresh();

                    $this->refreshFormData([
                        'status',
                        'request_uuid',
                        'sat_status_code',
                        'requested_at',
                        'finished_at',
                        'sat_message',
                        'error_message',
                    ]);

                    if (($result['ok'] ?? false) && ($result['ready'] ?? false)) {
                        Notification::make()
                            ->success()
                            ->title('Solicitud lista')
                            ->body($result['message'] ?? 'La solicitud ya tiene paquetes disponibles.')
                            ->send();

                        return;
                    }

                    if ($result['ok'] ?? false) {
                        Notification::make()
                            ->info()
                            ->title('Solicitud en proceso')
                            ->body($result['message'] ?? 'SAT sigue procesando la solicitud.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('No se pudo verificar')
                        ->body($result['message'] ?? 'Error desconocido.')
                        ->send();
                }),

            Actions\Action::make('downloadSatPackages')
                ->label('Descargar paquetes SAT')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'completed'
                    && filled(data_get($this->record->metadata, 'packages'))
                    && blank(data_get($this->record->metadata, 'downloaded_packages')))
                ->action(function (): void {
                    $result = app(SatDownloadPackageDownloader::class)->downloadPackages($this->record);

                    $this->record->refresh();

                    $this->refreshFormData([
                        'status',
                        'sat_message',
                        'error_message',
                    ]);

                    if ($result['ok'] ?? false) {
                        Notification::make()
                            ->success()
                            ->title('Paquetes descargados')
                            ->body($result['message'] ?? 'La descarga terminó correctamente.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('No se pudieron descargar todos los paquetes')
                        ->body($result['message'] ?? 'Error desconocido.')
                        ->send();
                }),

            Actions\Action::make('metadataAlreadyProcessed')
                ->label('Metadata ya procesada')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->disabled()
                ->visible(fn (): bool => $this->record->request_kind === 'metadata'
                    && filled(data_get($this->record->metadata, 'metadata_processed_at'))),

            Actions\Action::make('xmlAlreadyProcessed')
                ->label('XML ya procesados')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->disabled()
                ->visible(fn (): bool => $this->record->request_kind === 'xml'
                    && filled(data_get($this->record->metadata, 'xml_processed_at'))),

            Actions\Action::make('processSatXml')
                ->label('Procesar XML descargados')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Procesar XML descargados')
                ->modalDescription('Se leerán los XML contenidos en los paquetes ZIP descargados y se actualizarán CFDI, conceptos e impuestos.')
                ->visible(fn (): bool => $this->record->request_kind === 'xml'
                    && filled(data_get($this->record->metadata, 'downloaded_packages'))
                    && blank(data_get($this->record->metadata, 'xml_processed_at')))
                ->action(function (): void {
                    try {
                        $result = app(SatXmlPackageImporter::class)->importFromRequest($this->record);

                        $this->record->refresh();

                        Notification::make()
                            ->title('XML procesados')
                            ->body($result['message'] ?? 'Los XML fueron procesados correctamente.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudieron procesar los XML')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('processSatMetadata')
                ->label('Procesar metadata descargada')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Procesar metadata descargada')
                ->modalDescription('Esto leerá el paquete descargado del SAT y actualizará Fiscal SAT > CFDI con los comprobantes encontrados.')
                ->visible(fn (): bool => $this->record->request_kind === 'metadata'
                    && filled(data_get($this->record->metadata, 'downloaded_packages'))
                    && blank(data_get($this->record->metadata, 'metadata_processed_at')))
                ->action(function (): void {
                    $result = app(SatMetadataPackageImporter::class)->importFromRequest($this->record);

                    $this->record->refresh();

                    $this->refreshFormData([
                        'sat_message',
                        'error_message',
                    ]);

                    if ($result['ok'] ?? false) {
                        Notification::make()
                            ->success()
                            ->title('Metadata procesada')
                            ->body($result['message'] ?? 'La metadata fue procesada correctamente.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Metadata procesada con errores')
                        ->body($result['message'] ?? 'Revisa el detalle de errores.')
                        ->send();
                }),
        ];
    }
}
