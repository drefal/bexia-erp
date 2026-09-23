<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Support\Attendance\EmployeeCredentialPdfService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_credential')
                ->label('Descargar credencial QR')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    $companyId = (int) (Filament::getTenant()?->getKey() ?? 0);

                    return $companyId > 0
                        && (int) $record->company_id === $companyId
                        && app(EmployeeCredentialPdfService::class)
                            ->isEligible($record);
                })
                ->action(function () {
                    $record = $this->getRecord();
                    $companyId = (int) (Filament::getTenant()?->getKey() ?? 0);

                    if (
                        $companyId < 1
                        || (int) $record->company_id !== $companyId
                    ) {
                        abort(403);
                    }

                    $service = app(EmployeeCredentialPdfService::class);

                    if (! $service->isEligible($record)) {
                        abort(403);
                    }

                    $contents = $service->renderIndividual($record);
                    $filename = $service->individualFilename($record);

                    return response()->streamDownload(
                        static function () use ($contents): void {
                            echo $contents;
                        },
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
