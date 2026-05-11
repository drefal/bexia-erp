<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductObserver
{
    public function created(Product $product): void
    {
        if (! config('bexia_audit.products.enabled', true)) {
            return;
        }

        AuditLog::record(
            companyId: $product->company_id,
            userId: Auth::id(),
            auditableType: Product::class,
            auditableId: $product->id,
            event: 'created',
            fieldName: null,
            oldValue: null,
            newValue: $this->snapshot($product),
            metadata: [
                'label' => 'Producto creado',
            ],
        );
    }

    public function updated(Product $product): void
    {
        if (! config('bexia_audit.products.enabled', true)) {
            return;
        }

        $watchedFields = config('bexia_audit.products.fields', []);
        $changes = $product->getChanges();

        unset($changes['updated_at']);

        foreach ($changes as $field => $newValue) {
            if (! in_array($field, $watchedFields, true)) {
                continue;
            }

            $oldValue = $product->getOriginal($field);

            if ($this->normalize($oldValue) === $this->normalize($newValue)) {
                continue;
            }

            AuditLog::record(
                companyId: $product->company_id,
                userId: Auth::id(),
                auditableType: Product::class,
                auditableId: $product->id,
                event: 'updated',
                fieldName: $field,
                oldValue: $oldValue,
                newValue: $newValue,
                metadata: [
                    'label' => 'Campo de producto actualizado',
                    'product_name' => $product->name,
                    'internal_reference' => $product->internal_reference,
                    'sku' => $product->sku,
                ],
            );
        }
    }

    public function deleted(Product $product): void
    {
        if (! config('bexia_audit.products.enabled', true)) {
            return;
        }

        AuditLog::record(
            companyId: $product->company_id,
            userId: Auth::id(),
            auditableType: Product::class,
            auditableId: $product->id,
            event: 'deleted',
            fieldName: null,
            oldValue: $this->snapshot($product),
            newValue: null,
            metadata: [
                'label' => 'Producto eliminado',
            ],
        );
    }

    private function snapshot(Product $product): array
    {
        $fields = config('bexia_audit.products.snapshot_fields', []);

        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $product->{$field}])
            ->all();
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return $value;
    }
}
