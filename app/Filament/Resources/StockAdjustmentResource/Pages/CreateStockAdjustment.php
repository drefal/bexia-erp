<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStockAdjustment extends CreateRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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
        return $this->getResource()::getUrl('edit', [
            'record' => $this->record,
        ]);
    }
}
