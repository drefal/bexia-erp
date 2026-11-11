<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BEXIA_PURCHASE_SCOPE_FIX27
 *
 * Comprueba productos sin fusionar catalogos ni resolver SKUs.
 * Solo lectura; no modifica inventario.
 */
class PurchaseProductCompanyGuard
{
    public static function assertDocumentLines(
        string $table,
        string $foreignKey,
        int $documentId,
        int $companyId
    ): void {
        $allowed = [
            'purchase_request_lines' => 'purchase_request_id',
            'purchase_order_lines' => 'purchase_order_id',
        ];

        if (
            ! isset($allowed[$table])
            || $allowed[$table] !== $foreignKey
            || $documentId <= 0
            || $companyId <= 0
        ) {
            throw new RuntimeException(
                'No se pudo validar la empresa del documento de compra.'
            );
        }

        $lines = DB::table($table)
            ->where($foreignKey, $documentId)
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $lineId = (int) $line->id;
            $productId = (int) ($line->product_id ?? 0);
            $variantId = (int) ($line->product_variant_id ?? 0);

            if (
                isset($line->company_id)
                && (int) $line->company_id !== $companyId
            ) {
                throw new RuntimeException(
                    "La linea {$lineId} pertenece a otra empresa."
                );
            }

            $product = $productId > 0
                ? DB::table('products')
                    ->where('id', $productId)
                    ->first(['id', 'company_id', 'parent_product_id'])
                : null;

            if (
                ! $product
                || (int) ($product->company_id ?? 0) !== $companyId
                || ! empty($product->parent_product_id)
            ) {
                throw new RuntimeException(
                    "No se puede procesar la linea {$lineId}: "
                    . "el producto {$productId} no pertenece "
                    . "al catalogo de la empresa {$companyId}."
                );
            }

            if ($variantId > 0) {
                $variant = DB::table('products')
                    ->where('id', $variantId)
                    ->first(['id', 'company_id', 'parent_product_id']);

                if (
                    ! $variant
                    || (int) ($variant->company_id ?? 0) !== $companyId
                    || (int) ($variant->parent_product_id ?? 0) !== $productId
                ) {
                    throw new RuntimeException(
                        "No se puede procesar la linea {$lineId}: "
                        . "la variante {$variantId} no corresponde "
                        . "al producto y empresa del documento."
                    );
                }
            }
        }
    }
}
