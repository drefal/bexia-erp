<?php

namespace App\Filament\Resources\EmployeeCredentialResource\Pages;

use App\Filament\Resources\EmployeeCredentialResource;
use App\Support\Attendance\EmployeeCredentialPdfService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeCredentials extends ListRecords
{
    protected static string $resource = EmployeeCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_all_credentials')
                ->label('Descargar todas')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Descargar todas las credenciales QR')
                ->modalDescription('Se generara un PDF carta con todas las credenciales de empleados activos de esta empresa que tengan QR de asistencia habilitado.')
                ->modalSubmitActionLabel('Generar PDF')
                ->action(function () {
                    $companyId = (int) (Filament::getTenant()?->getKey() ?? 0);

                    if ($companyId < 1) {
                        abort(403);
                    }

                    $employees = EmployeeCredentialResource::eligibleQuery($companyId)
                        ->orderBy('name')
                        ->get();

                    if ($employees->isEmpty()) {
                        Notification::make()
                            ->title('No hay credenciales para descargar')
                            ->body('No hay empleados activos con QR habilitado y token disponible en esta empresa.')
                            ->warning()
                            ->send();

                        return null;
                    }

                    $service = app(EmployeeCredentialPdfService::class);
                    $contents = $service->renderBulk($employees);
                    $companyName = (string) ($employees->first()?->company?->name ?: Filament::getTenant()?->name ?: 'empresa');
                    $filename = $service->bulkFilename($companyName);

                    return response()->streamDownload(
                        static function () use ($contents): void {
                            echo $contents;
                        },
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }
}
