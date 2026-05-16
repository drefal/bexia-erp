<?php

namespace App\Filament\Resources\StockSerialNumberResource\Pages;

use App\Filament\Resources\StockSerialNumberResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStockSerialNumber extends CreateRecord
{
    protected static string $resource = StockSerialNumberResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Número de serie creado correctamente';
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Número de serie creado')
            ->body('El número de serie fue guardado correctamente.')
            ->success()
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->validateSerialData($data);
    }

    protected function validateSerialData(array $data, ?int $ignoreId = null): array
    {
        $companyId = $this->companyId($data);
        $productId = (int) ($data['product_id'] ?? 0);
        $variantId = ! empty($data['product_variant_id']) ? (int) $data['product_variant_id'] : null;
        $lotId = ! empty($data['lot_id']) ? (int) $data['lot_id'] : null;
        $serialNumber = trim((string) ($data['serial_number'] ?? ''));

        if ($productId <= 0) {
            $this->notifyError('No se pudo crear la serie', 'Selecciona un producto.');
            throw ValidationException::withMessages(['product_id' => 'Selecciona un producto.']);
        }

        if ($serialNumber === '') {
            $this->notifyError('No se pudo crear la serie', 'Captura el número de serie.');
            throw ValidationException::withMessages(['serial_number' => 'Captura el número de serie.']);
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            $this->notifyError('No se pudo crear la serie', 'El producto seleccionado no existe.');
            throw ValidationException::withMessages(['product_id' => 'El producto seleccionado no existe.']);
        }

        if ((string) ($product->tracking ?? 'none') !== 'serial') {
            $this->notifyError(
                'Producto sin seguimiento por serie',
                'Primero cambia el producto a seguimiento "Por número de serie".'
            );

            throw ValidationException::withMessages([
                'product_id' => 'Este producto no maneja números de serie. Primero cambia su seguimiento en Productos.',
            ]);
        }

        if ($variantId) {
            $variant = DB::table('products')->where('id', $variantId)->first();

            if (! $variant || (int) ($variant->parent_product_id ?? 0) !== $productId) {
                $this->notifyError('Variante incorrecta', 'La variante seleccionada no pertenece al producto.');
                throw ValidationException::withMessages(['product_variant_id' => 'La variante seleccionada no pertenece al producto.']);
            }
        }

        if ($lotId) {
            $lot = DB::table('stock_lots')->where('id', $lotId)->first();

            if (! $lot) {
                $this->notifyError('Lote incorrecto', 'El lote seleccionado no existe.');
                throw ValidationException::withMessages(['lot_id' => 'El lote seleccionado no existe.']);
            }

            if ((int) ($lot->product_id ?? 0) !== $productId) {
                $this->notifyError('Lote incorrecto', 'El lote seleccionado pertenece a otro producto.');
                throw ValidationException::withMessages(['lot_id' => 'El lote seleccionado pertenece a otro producto.']);
            }

            $lotVariantId = ! empty($lot->product_variant_id) ? (int) $lot->product_variant_id : null;

            if ($lotVariantId !== $variantId) {
                $this->notifyError('Lote incorrecto', 'El lote seleccionado no corresponde a la variante seleccionada.');
                throw ValidationException::withMessages(['lot_id' => 'El lote seleccionado no corresponde a la variante seleccionada.']);
            }
        }

        $query = DB::table('stock_serial_numbers')
            ->where('product_id', $productId)
            ->whereRaw('LOWER(serial_number) = LOWER(?)', [$serialNumber]);

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
            $this->notifyError('Serie duplicada', 'Ya existe este número de serie para el mismo producto y variante.');
            throw ValidationException::withMessages(['serial_number' => 'Ya existe este número de serie para el mismo producto y variante.']);
        }

        $data['company_id'] = $companyId;
        $data['product_variant_id'] = $variantId;
        $data['lot_id'] = $lotId;
        $data['serial_number'] = $serialNumber;
        $data['status'] = $data['status'] ?: 'available';
        $data['source_type'] = $data['source_type'] ?: 'manual';

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
