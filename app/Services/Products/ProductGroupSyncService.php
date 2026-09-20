<?php

namespace App\Services\Products;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductGroupSyncService
{
    /**
     * Replica un producto a todas las empresas activas de su mismo grupo.
     *
     * Cada empresa conserva su propio product.id.
     * group_product_uuid identifica el mismo producto lógico dentro del grupo.
     */
    public function syncCreatedProduct(Product $source): array
    {
        $source->refresh();

        $company = Company::query()->find($source->company_id);

        if (! $company || ! $company->company_group_id) {
            return [];
        }

        $targets = Company::query()
            ->where('company_group_id', $company->company_group_id)
            ->where('active', true)
            ->whereKeyNot($company->getKey())
            ->orderBy('id')
            ->get();

        if ($targets->isEmpty()) {
            return [];
        }

        return DB::transaction(function () use ($source, $targets): array {
            $groupUuid = trim(
                (string) ($source->group_product_uuid ?? '')
            );

            if ($groupUuid === '') {
                $groupUuid = (string) Str::uuid();

                $source->forceFill([
                    'group_product_uuid' => $groupUuid,
                ])->saveQuietly();

                $source->refresh();
            }

            $result = [];

            foreach ($targets as $target) {
                $result[(int) $target->id] = $this->syncToCompany(
                    $source,
                    (int) $target->id,
                    $groupUuid
                );
            }

            return $result;
        });
    }

    protected function syncToCompany(
        Product $source,
        int $targetCompanyId,
        string $groupUuid
    ): int {
        $existing = Product::query()
            ->where('company_id', $targetCompanyId)
            ->where('group_product_uuid', $groupUuid)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $this->assertNoIdentifierCollision(
            $source,
            $targetCompanyId
        );

        $attributes = $source->getAttributes();

        foreach ([
            'id',
            'company_id',
            'created_at',
            'updated_at',

            // Linaje Odoo: NO debe duplicarse.
            'odoo_product_id',
            'odoo_template_id',
            'odoo_category_id',
            'odoo_category_name',
            'odoo_tracking',
            'odoo_migration_notes',
            'odoo_raw_json',
        ] as $column) {
            unset($attributes[$column]);
        }

        $attributes['company_id'] = $targetCompanyId;
        $attributes['group_product_uuid'] = $groupUuid;

        $attributes['product_template_id'] = null;

        $attributes['product_category_id'] =
            $this->mapCategory(
                $source->product_category_id,
                $targetCompanyId
            );

        $attributes['inventory_unit_id'] =
            $this->mapInventoryUnit(
                $source->inventory_unit_id,
                $targetCompanyId
            );

        foreach ([
            'inventory_account_id',
            'cogs_account_id',
            'sales_income_account_id',
        ] as $column) {
            $attributes[$column] =
                $this->mapAccountingAccount(
                    $source->{$column},
                    $targetCompanyId
                );
        }

        /*
         * Estas relaciones pueden depender de permisos o catálogos
         * específicos por empresa. No se copian ciegamente.
         */
        $attributes['preferred_supplier_id'] = null;
        $attributes['responsible_user_id'] = null;

        $attributes['parent_product_id'] =
            $this->mapParentProduct(
                $source,
                $targetCompanyId
            );

        $extra = $source->extra_attributes;

        if (! is_array($extra)) {
            $extra = [];
        }

        $extra['bexia_group_product_uuid'] = $groupUuid;
        $extra['bexia_group_sync_source_company_id'] =
            (int) $source->company_id;
        $extra['bexia_group_sync_source_product_id'] =
            (int) $source->id;

        $attributes['extra_attributes'] = $extra;

        $replica = new Product();
        $replica->forceFill($attributes);
        $replica->saveQuietly();

        $this->copyTaxRates(
            $source,
            $replica
        );

        $this->copyImages(
            $source,
            $replica
        );

        $this->copyPurchaseUnits(
            $source,
            $replica
        );

        return (int) $replica->id;
    }

    protected function assertNoIdentifierCollision(
        Product $source,
        int $targetCompanyId
    ): void {
        $ids = [];

        $reference = trim(
            (string) ($source->internal_reference ?? '')
        );

        if ($reference !== '') {
            $matches = Product::query()
                ->where('company_id', $targetCompanyId)
                ->where('is_active', true)
                ->whereRaw(
                    'LOWER(TRIM(internal_reference)) = ?',
                    [mb_strtolower($reference, 'UTF-8')]
                )
                ->pluck('id');

            foreach ($matches as $id) {
                $ids[(int) $id] = true;
            }
        }

        $sku = trim(
            (string) ($source->sku ?? '')
        );

        if ($sku !== '') {
            $matches = Product::query()
                ->where('company_id', $targetCompanyId)
                ->where('is_active', true)
                ->whereRaw(
                    'LOWER(TRIM(sku)) = ?',
                    [mb_strtolower($sku, 'UTF-8')]
                )
                ->pluck('id');

            foreach ($matches as $id) {
                $ids[(int) $id] = true;
            }
        }

        $barcode = trim(
            (string) ($source->barcode ?? '')
        );

        if ($barcode !== '') {
            $matches = Product::query()
                ->where('company_id', $targetCompanyId)
                ->where('is_active', true)
                ->whereRaw(
                    'LOWER(TRIM(barcode)) = ?',
                    [mb_strtolower($barcode, 'UTF-8')]
                )
                ->pluck('id');

            foreach ($matches as $id) {
                $ids[(int) $id] = true;
            }
        }

        if (! empty($ids)) {
            throw new RuntimeException(
                'No se puede sincronizar el producto ' .
                $source->id .
                ' a company_id=' .
                $targetCompanyId .
                ': existe un producto activo con la misma ' .
                'referencia, SKU o codigo de barras.'
            );
        }
    }

    protected function mapCategory(
        mixed $sourceCategoryId,
        int $targetCompanyId
    ): ?int {
        $sourceCategoryId = (int) ($sourceCategoryId ?? 0);

        if ($sourceCategoryId <= 0) {
            return null;
        }

        $source = DB::table('product_categories')
            ->where('id', $sourceCategoryId)
            ->first();

        if (! $source) {
            return null;
        }

        $code = trim((string) $source->code);

        if ($code === '') {
            throw new RuntimeException(
                "Categoria origen {$sourceCategoryId} sin code."
            );
        }

        $existing = DB::table('product_categories')
            ->where('company_id', $targetCompanyId)
            ->whereRaw(
                'LOWER(TRIM(code)) = ?',
                [mb_strtolower($code, 'UTF-8')]
            )
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $attributes = (array) $source;

        foreach ([
            'id',
            'company_id',
            'created_at',
            'updated_at',
        ] as $column) {
            unset($attributes[$column]);
        }

        $attributes['company_id'] = $targetCompanyId;

        $attributes['parent_id'] =
            ! empty($source->parent_id)
                ? $this->mapCategory(
                    $source->parent_id,
                    $targetCompanyId
                )
                : null;

        foreach ([
            'inventory_account_id',
            'cogs_account_id',
            'sales_income_account_id',
        ] as $column) {
            if (array_key_exists($column, $attributes)) {
                $attributes[$column] =
                    $this->mapAccountingAccount(
                        $source->{$column} ?? null,
                        $targetCompanyId
                    );
            }
        }

        $attributes['created_at'] = now();
        $attributes['updated_at'] = now();

        return (int) DB::table('product_categories')
            ->insertGetId($attributes);
    }

    protected function mapInventoryUnit(
        mixed $sourceUnitId,
        int $targetCompanyId
    ): ?int {
        $sourceUnitId = (int) ($sourceUnitId ?? 0);

        if ($sourceUnitId <= 0) {
            return null;
        }

        $source = DB::table('inventory_units')
            ->where('id', $sourceUnitId)
            ->first();

        if (! $source) {
            return null;
        }

        $code = trim((string) $source->code);

        if ($code === '') {
            throw new RuntimeException(
                "Unidad origen {$sourceUnitId} sin code."
            );
        }

        $existing = DB::table('inventory_units')
            ->where('company_id', $targetCompanyId)
            ->whereRaw(
                'LOWER(TRIM(code)) = ?',
                [mb_strtolower($code, 'UTF-8')]
            )
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $attributes = (array) $source;

        foreach ([
            'id',
            'company_id',
            'created_at',
            'updated_at',
        ] as $column) {
            unset($attributes[$column]);
        }

        $attributes['company_id'] = $targetCompanyId;
        $attributes['created_at'] = now();
        $attributes['updated_at'] = now();

        return (int) DB::table('inventory_units')
            ->insertGetId($attributes);
    }

    protected function mapAccountingAccount(
        mixed $sourceAccountId,
        int $targetCompanyId
    ): ?int {
        $sourceAccountId = (int) ($sourceAccountId ?? 0);

        if ($sourceAccountId <= 0) {
            return null;
        }

        $source = DB::table('accounting_accounts')
            ->where('id', $sourceAccountId)
            ->first();

        if (! $source) {
            return null;
        }

        $code = trim((string) $source->code);

        if ($code === '') {
            return null;
        }

        $targetId = DB::table('accounting_accounts')
            ->where('company_id', $targetCompanyId)
            ->whereRaw(
                'LOWER(TRIM(code)) = ?',
                [mb_strtolower($code, 'UTF-8')]
            )
            ->value('id');

        return $targetId
            ? (int) $targetId
            : null;
    }

    protected function mapParentProduct(
        Product $source,
        int $targetCompanyId
    ): ?int {
        $parentId = (int) ($source->parent_product_id ?? 0);

        if ($parentId <= 0) {
            return null;
        }

        $parent = Product::query()->find($parentId);

        if (! $parent) {
            throw new RuntimeException(
                "No existe producto padre {$parentId}."
            );
        }

        if (blank($parent->group_product_uuid)) {
            $this->syncCreatedProduct($parent);
            $parent->refresh();
        }

        $targetParentId = Product::query()
            ->where('company_id', $targetCompanyId)
            ->where(
                'group_product_uuid',
                $parent->group_product_uuid
            )
            ->value('id');

        if (! $targetParentId) {
            throw new RuntimeException(
                'No se encontro producto padre equivalente ' .
                "en company_id={$targetCompanyId}."
            );
        }

        return (int) $targetParentId;
    }

    protected function copyTaxRates(
        Product $source,
        Product $target
    ): void {
        $links = DB::table('product_tax_rates as ptr')
            ->join(
                'tax_rates as t',
                't.id',
                '=',
                'ptr.tax_rate_id'
            )
            ->where('ptr.product_id', $source->id)
            ->where('ptr.is_active', true)
            ->select([
                'ptr.usage_type',
                't.id as source_tax_id',
                't.code',
                't.name',
                't.tax_type',
                't.factor_type',
                't.rate',
                't.is_withholding',
            ])
            ->get();

        foreach ($links as $link) {
            $targetTaxId = $this->resolveTargetTaxRate(
                $link,
                (int) $target->company_id
            );

            DB::table('product_tax_rates')->insert([
                'company_id' => $target->company_id,
                'product_id' => $target->id,
                'tax_rate_id' => $targetTaxId,
                'usage_type' => $link->usage_type,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function resolveTargetTaxRate(
        object $sourceTax,
        int $targetCompanyId
    ): int {
        $candidates = DB::table('tax_rates')
            ->where('company_id', $targetCompanyId)
            ->where('is_active', true)
            ->whereRaw(
                'LOWER(TRIM(tax_type)) = ?',
                [
                    mb_strtolower(
                        trim((string) $sourceTax->tax_type),
                        'UTF-8'
                    ),
                ]
            )
            ->whereRaw(
                'LOWER(TRIM(factor_type)) = ?',
                [
                    mb_strtolower(
                        trim((string) $sourceTax->factor_type),
                        'UTF-8'
                    ),
                ]
            )
            ->where('rate', $sourceTax->rate)
            ->where(
                'is_withholding',
                (bool) $sourceTax->is_withholding
            )
            ->get();

        if ($candidates->isEmpty()) {
            throw new RuntimeException(
                'No existe impuesto equivalente para company_id=' .
                $targetCompanyId .
                ', source_tax_id=' .
                $sourceTax->source_tax_id
            );
        }

        $sourceName = $this->normalizeText(
            $sourceTax->name
        );

        $exactName = $candidates->filter(
            fn ($candidate): bool =>
                $this->normalizeText($candidate->name) ===
                $sourceName
        );

        if ($exactName->count() === 1) {
            return (int) $exactName->first()->id;
        }

        $usageType = strtolower(
            trim((string) $sourceTax->usage_type)
        );

        $usageCandidates = $candidates->filter(
            function ($candidate) use ($usageType): bool {
                $name = $this->normalizeText(
                    $candidate->name
                );

                if ($usageType === 'sale') {
                    return preg_match(
                        '/\bVENTAS?\b/u',
                        $name
                    ) === 1;
                }

                if ($usageType === 'purchase') {
                    return preg_match(
                        '/\bCOMPRAS?\b/u',
                        $name
                    ) === 1;
                }

                return false;
            }
        );

        if ($usageCandidates->count() === 1) {
            return (int) $usageCandidates->first()->id;
        }

        $sourceCode = $this->normalizeText(
            $sourceTax->code
        );

        $exactCode = $candidates->filter(
            fn ($candidate): bool =>
                $this->normalizeText($candidate->code) ===
                $sourceCode
        );

        if ($exactCode->count() === 1) {
            return (int) $exactCode->first()->id;
        }

        if ($candidates->count() === 1) {
            return (int) $candidates->first()->id;
        }

        throw new RuntimeException(
            'Impuesto ambiguo para company_id=' .
            $targetCompanyId .
            ', usage=' .
            $usageType .
            ', source_tax_id=' .
            $sourceTax->source_tax_id
        );
    }

    protected function copyImages(
        Product $source,
        Product $target
    ): void {
        $rows = DB::table('product_images')
            ->where('product_id', $source->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $attributes = (array) $row;

            foreach ([
                'id',
                'company_id',
                'product_id',
                'product_template_id',
                'created_at',
                'updated_at',

                // No duplicar identidad legacy Odoo.
                'source_system',
                'source_model',
                'source_id',
                'source_attachment_id',
                'source_reference',
                'legacy_reference',
                'legacy_company_id',
                'legacy_payload',
                'migrated_at',
                'migration_batch_id',
            ] as $column) {
                unset($attributes[$column]);
            }

            $attributes['company_id'] = $target->company_id;
            $attributes['product_id'] = $target->id;
            $attributes['product_template_id'] = null;

            if (array_key_exists('is_legacy', $attributes)) {
                $attributes['is_legacy'] = false;
            }

            if (array_key_exists('locked', $attributes)) {
                $attributes['locked'] = false;
            }

            $attributes['created_at'] = now();
            $attributes['updated_at'] = now();

            DB::table('product_images')->insert(
                $attributes
            );
        }
    }

    protected function copyPurchaseUnits(
        Product $source,
        Product $target
    ): void {
        $rows = DB::table('product_purchase_units')
            ->where('product_id', $source->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $attributes = (array) $row;

            foreach ([
                'id',
                'company_id',
                'product_id',
                'created_at',
                'updated_at',
            ] as $column) {
                unset($attributes[$column]);
            }

            $attributes['company_id'] = $target->company_id;
            $attributes['product_id'] = $target->id;
            $attributes['created_at'] = now();
            $attributes['updated_at'] = now();

            DB::table('product_purchase_units')->insert(
                $attributes
            );
        }
    }

    protected function normalizeText(
        mixed $value
    ): string {
        return mb_strtoupper(
            trim((string) $value),
            'UTF-8'
        );
    }
}
