<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStockAdjustment extends CreateRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    // BEXIA_V5836E_DISABLE_CREATE_ANOTHER
    // El flujo del ajuste requiere crear el encabezado y continuar a captura de líneas.
    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reason' => 'Captura el motivo del ajuste antes de guardarlo.',
            ]);
        }

        $data['reason'] = $reason;

        StockAdjustmentResource::assertAdjustmentLinesCanBeSaved(
            isset($data['location_id']) ? (int) $data['location_id'] : null,
            $data['lines'] ?? []
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Ajuste creado')
            ->body('El ajuste quedó en borrador. Revísalo y después confirma para afectar existencias.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        // BEXIA_V5728B_AJUSTES_INVENTARIO_LINES_FINAL
        return $this->getResource()::getUrl('lines', [
            'record' => $this->record,
        ]);
    }
}
