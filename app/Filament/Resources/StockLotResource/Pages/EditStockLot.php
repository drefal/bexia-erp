<?php

namespace App\Filament\Resources\StockLotResource\Pages;

use App\Filament\Resources\StockLotResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditStockLot extends EditRecord
{
    protected static string $resource = StockLotResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Lote actualizado correctamente';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->validateLotData($data, (int) $this->record->getKey());
    }

    protected function validateLotData(array $data, ?int $ignoreId = null): array
    {
        $companyId = $this->companyId($data);
        $productId = (int) ($data['product_id'] ?? 0);
        $variantId = ! empty($data['product_variant_id']) ? (int) $data['product_variant_id'] : null;
        $lotNumber = trim((string) ($data['lot_number'] ?? ''));

        if ($productId <= 0) {
            $this->notifyError('No se pudo guardar el lote', 'Selecciona un producto.');
            throw ValidationException::withMessages(['product_id' => 'Selecciona un producto.']);
        }

        if ($lotNumber === '') {
            $this->notifyError('No se pudo guardar el lote', 'Captura el número de lote.');
            throw ValidationException::withMessages(['lot_number' => 'Captura el número de lote.']);
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            $this->notifyError('No se pudo guardar el lote', 'El producto seleccionado no existe.');
            throw ValidationException::withMessages(['product_id' => 'El producto seleccionado no existe.']);
        }

        $tracking = (string) ($product->tracking ?? 'none');

        if (! in_array($tracking, ['lot', 'serial'], true)) {
            $this->notifyError(
                'Producto sin seguimiento por lote',
                'Primero cambia el producto a seguimiento "Por lote" o "Por número de serie".'
            );

            throw ValidationException::withMessages([
                'product_id' => 'Este producto no maneja lotes. Primero cambia su seguimiento en Productos.',
            ]);
        }

        if ($variantId) {
            $variant = DB::table('products')->where('id', $variantId)->first();

            if (! $variant || (int) ($variant->parent_product_id ?? 0) !== $productId) {
                $this->notifyError('Variante incorrecta', 'La variante seleccionada no pertenece al producto.');
                throw ValidationException::withMessages(['product_variant_id' => 'La variante seleccionada no pertenece al producto.']);
            }
        }

        $query = DB::table('stock_lots')
            ->where('product_id', $productId)
            ->whereRaw('LOWER(lot_number) = LOWER(?)', [$lotNumber]);

        $companyId
            ? $query->where('company_id', $companyId)
            : $query->whereNull('company_id');

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            $this->notifyError('Lote duplicado', 'Ya existe este lote para el mismo producto y variante.');
            throw ValidationException::withMessages(['lot_number' => 'Ya existe este lote para el mismo producto y variante.']);
        }

        $data['company_id'] = $companyId;
        $data['product_variant_id'] = $variantId;
        $data['lot_number'] = $lotNumber;
        $data['status'] = $data['status'] ?: 'available';

        return $data;
    }

    protected function notifyError(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->persistent()
            ->send();
    }

    protected function companyId(array $data): ?int
    {
        if (! empty($data['company_id'])) {
            return (int) $data['company_id'];
        }

        $tenant = Filament::getTenant();

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return null;
    }
}
