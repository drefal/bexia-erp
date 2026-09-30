<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /*
         * La cabecera siempre nace en borrador.
         * El estado no se acepta desde el cliente.
         */
        $data['status'] = 'draft';

        /*
         * Esta pantalla crea únicamente la cabecera/borrador.
         *
         * Si el usuario ya seleccionó un tipo de operación, respetarlo.
         * Si no lo hizo, intentar resolver automáticamente Traslado interno
         * para la empresa/almacén seleccionados.
         */
        if (! empty($data['stock_operation_type_id'])) {
            return $data;
        }

        $companyId = (int) (
            $data['company_id']
            ?? \Filament\Facades\Filament::getTenant()?->getKey()
            ?? auth()->user()?->company_id
            ?? 0
        );

        $warehouseId = (int) ($data['warehouse_id'] ?? 0);

        if ($companyId <= 0 || $warehouseId <= 0) {
            return $data;
        }

        $operationIds = DB::table('stock_operation_types')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('operation_kind', 'internal_transfer')
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id');

        /*
         * Fail-safe:
         * sólo autoseleccionar cuando existe exactamente una operación
         * de traslado interno para esa empresa/almacén.
         */
        if ($operationIds->count() === 1) {
            $data['stock_operation_type_id'] = (int) $operationIds->first();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Traslado creado')
            ->body(
                'El traslado quedó en borrador. '
                . 'Ahora agrega los productos y después despáchalo.'
            )
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
