<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Models\Product;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $companyId = Filament::getTenant()?->getKey();

        if ($companyId) {
            $data['company_id'] = $companyId;
        }

        // BEXIA_V5550E_CREATE_INTERNAL_REFERENCE_MUTATE_BEFORE_CREATE
        $reference = trim((string) ($data['internal_reference'] ?? ''));

        if ($reference !== '') {
            $query = \App\Models\Product::query()
                ->whereRaw('LOWER(TRIM(internal_reference)) = ?', [mb_strtolower($reference, 'UTF-8')]);

            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id')) {
                $checkCompanyId = (int) (($data['company_id'] ?? null) ?: (\Filament\Facades\Filament::getTenant()?->getKey() ?: 0));

                if ($checkCompanyId > 0) {
                    $query->where('company_id', $checkCompanyId);
                } else {
                    $query->whereNull('company_id');
                }
            }

            if ($query->exists()) {
                $data['internal_reference'] = null;

                if (property_exists($this, 'data') && is_array($this->data)) {
                    $this->data['internal_reference'] = null;
                }

                $this->form->fill($data);

                // BEXIA_V5550F_INTERNAL_REFERENCE_DUPLICATE_MODAL_DISPATCH_CREATE
                $this->dispatch(
                    'bexia-internal-reference-duplicate-modal',
                    title: 'Referencia interna duplicada',
                    message: 'La referencia interna ya existe en otro producto de esta empresa. Se limpió el campo para capturar una referencia diferente.',
                );

                throw new \Filament\Support\Exceptions\Halt();
            }

            $data['internal_reference'] = $reference;
        } else {
            $data['internal_reference'] = null;
        }

        $data['available_in_pos'] = array_key_exists('available_in_pos', $data)
            ? (bool) $data['available_in_pos']
            : true;

        /*
         * Las cuentas contables del producto todavía no se capturan en el formulario.
         * No debemos leer directamente inventory_account_id, cogs_account_id o
         * sales_income_account_id porque pueden no existir en $data.
         */
        foreach ([
            'inventory_account_id',
            'cogs_account_id',
            'sales_income_account_id',
        ] as $accountField) {
            if (! array_key_exists($accountField, $data)) {
                continue;
            }

            if ($data[$accountField] === '' || $data[$accountField] === false) {
                $data[$accountField] = null;
            }
        }

        $data = $this->applyVariantCreateData($data);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // variant_form_state_v7
        $data = $this->mergeFormStateForVariant($data);

        $parent = $this->getVariantParentFromRequest();

        if ($parent) {
            $data = $this->prepareVariantDataForCreate($data, $parent);
        }

        $data = $this->fillRequiredProductColumnsForCreate($data);

        $modelClass = static::getModel();

        /** @var \Illuminate\Database\Eloquent\Model $record */
        $record = new $modelClass();
        $record->forceFill($data);
        $record->save();

        return $record;
    }



    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Guardar producto');
    }

    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Guardar y crear otro producto');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()
            ->label('Cancelar');
    }

    protected function getVariantParentFromRequest(): ?Product
    {
        $parentId = (int) request()->query('parent_product_id');

        if ($parentId <= 0) {
            return null;
        }

        return Product::query()->find($parentId);
    }


    public function mount(): void
    {
        parent::mount();

        $parent = $this->getVariantParentFromRequest();

        if (! $parent) {
            return;
        }

        $this->form->fill([
            'company_id' => $parent->company_id,
            'name' => $parent->name,
            'parent_product_id' => $parent->id,
            // variant_reference_mount_v1
            'internal_reference' => $this->nextVariantReference($parent),
            'is_variant' => true,
            'has_variants' => false,
            'product_category_id' => $parent->product_category_id,
            'inventory_unit_id' => $parent->inventory_unit_id,
            'product_type' => $parent->product_type,
            'tracking' => $parent->tracking,
            'costing_method' => $parent->costing_method,
            'can_be_sold' => $parent->can_be_sold,
            'can_be_purchased' => $parent->can_be_purchased,
            'available_in_pos' => $parent->available_in_pos,
            'is_active' => true,
            'sat_unit_code' => $parent->sat_unit_code,
            'image_path' => $parent->image_path,
            'sale_price' => $parent->sale_price,
            'standard_cost' => $parent->standard_cost,
            'purchase_price' => $parent->purchase_price,
            'variant_group' => request()->query('variant_group') ?: 'Color',
        ]);
    }


    
    protected function afterCreate(): void
    {
        // sync_product_taxes_after_create_v1
        $this->syncProductTaxRatesFromForm();

        $record = $this->record;

        if (! $record || ! ($record->is_variant ?? false) || ! $record->parent_product_id) {
            return;
        }

        $parent = Product::query()->find($record->parent_product_id);

        if (! $parent) {
            return;
        }

        $variantValue = trim((string) ($record->variant_value ?: $record->variant_name));

        if ($variantValue === '') {
            return;
        }

        $finalName = trim((string) $parent->name) . ' - ' . $variantValue;

        $record->forceFill([
            'name' => $finalName,
            'variant_name' => $record->variant_name ?: $variantValue,
            'has_variants' => false,
            'is_variant' => true,
        ])->saveQuietly();

        $parent->forceFill([
            'has_variants' => true,
            'is_variant' => false,
            'parent_product_id' => null,
        ])->saveQuietly();
    }



    public function getTitle(): string
    {
        return $this->getVariantParentFromRequest()
            ? 'Nueva variante'
            : 'Nuevo producto';
    }


    public function getSubheading(): string|HtmlString|null
    {
        $parent = $this->getVariantParentFromRequest();

        if (! $parent) {
            return null;
        }

        $url = ProductResource::getUrl('edit', ['record' => $parent]);

        return new HtmlString(
            '<nav class="flex items-center gap-1 text-sm text-gray-600">' .
            '<a href="' . e($url) . '" class="text-primary-600 hover:text-primary-500 hover:underline font-medium">' . e($parent->name) . '</a>' .
            '<span class="text-gray-400 px-1">/</span>' .
            '<span>Nueva variante</span>' .
            '</nav>'
        );
    }


    protected function applyVariantCreateData(array $data): array
    {
        $parent = $this->getVariantParentFromRequest();

        if (! $parent) {
            return $data;
        }

        $variantGroup = trim((string) ($data['variant_group'] ?? 'Color'));
        $variantValue = trim((string) ($data['variant_value'] ?? $data['variant_name'] ?? ''));

        if ($variantValue === '') {
            return $data;
        }

        $data['company_id'] = $parent->company_id;
        $data['parent_product_id'] = $parent->id;
        $data['is_variant'] = true;
        $data['has_variants'] = false;

        $data['product_category_id'] = $data['product_category_id'] ?? $parent->product_category_id;
        $data['inventory_unit_id'] = $data['inventory_unit_id'] ?? $parent->inventory_unit_id;
        $data['product_type'] = $data['product_type'] ?? $parent->product_type;
        $data['tracking'] = $data['tracking'] ?? $parent->tracking;
        $data['costing_method'] = $data['costing_method'] ?? $parent->costing_method;
        $data['sat_unit_code'] = $data['sat_unit_code'] ?? $parent->sat_unit_code;

        // variant_invoice_policy_fix_v2
        if (blank($data['invoice_policy'] ?? null)) {
            $data['invoice_policy'] = $parent->invoice_policy
                ?: DB::table('products')
                    ->where('company_id', $parent->company_id)
                    ->whereNotNull('invoice_policy')
                    ->value('invoice_policy')
                ?: 'delivered_quantities';
        }

        if (empty($data['image_path'])) {
            $data['image_path'] = $parent->image_path;
        }

        $data['variant_group'] = $variantGroup;
        $data['variant_value'] = $variantValue;
        $data['variant_name'] = $data['variant_name'] ?: $variantValue;
        $data['variant_signature'] = Str::slug($variantGroup . ':' . $variantValue);

        // Para variantes, el nombre visible se genera automáticamente.
        // Nombre final automático de variante.
        $data['name'] = trim((string) $parent->name) . ' - ' . $variantValue;

        if (blank($data['internal_reference'] ?? null)) {
            // variant_reference_save_v1
            $data['internal_reference'] = $this->nextVariantReference($parent);
        }

        $extra = $data['extra_attributes'] ?? [];

        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($extra)) {
            $extra = [];
        }

        $extra['import_source'] = $extra['import_source'] ?? 'manual_variant';
        $extra['parent_product_id'] = $parent->id;
        $extra['variant_group'] = $variantGroup;
        $extra['variant_value'] = $variantValue;

        $data['extra_attributes'] = $extra;

        return $data;
    }


    protected function nextVariantReference(Product $parent): string
    {
        $baseReference = trim((string) $parent->internal_reference);

        if ($baseReference === '') {
            $baseReference = 'P' . $parent->id;
        }

        $references = Product::query()
            ->where('company_id', $parent->company_id)
            ->where('internal_reference', 'like', $baseReference . '-%')
            ->pluck('internal_reference')
            ->filter()
            ->values();

        $max = 0;

        foreach ($references as $reference) {
            $reference = trim((string) $reference);

            if (preg_match('/^' . preg_quote($baseReference, '/') . '-(\d+)$/', $reference, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        do {
            $candidate = $baseReference . '-' . $next;
            $next++;
        } while (
            Product::query()
                ->where('company_id', $parent->company_id)
                ->where('internal_reference', $candidate)
                ->exists()
        );

        return $candidate;
    }


    protected function finalizeVariantCreateData(array $data): array
    {
        $parent = $this->getVariantParentFromRequest();

        if (! $parent) {
            return $data;
        }

        // validate_and_name_variant_v6
        $variantGroup = trim((string) ($data['variant_group'] ?? 'Color'));
        $variantValue = trim((string) ($data['variant_value'] ?? ''));

        if ($variantGroup === '') {
            $variantGroup = 'Color';
        }

        if ($variantValue === '') {
            throw ValidationException::withMessages([
                'variant_value' => 'Captura el valor de la variante antes de guardar.',
            ]);
        }

        $data = $this->applyVariantCreateData($data);

        $data['company_id'] = $parent->company_id;
        $data['parent_product_id'] = $parent->id;
        $data['is_variant'] = true;
        $data['has_variants'] = false;

        $data['variant_group'] = $variantGroup;
        $data['variant_value'] = $variantValue;
        $data['variant_name'] = $data['variant_name'] ?: $variantValue;
        $data['variant_signature'] = \Illuminate\Support\Str::slug($variantGroup . ':' . $variantValue);

        $data['name'] = trim((string) $parent->name) . ' - ' . $variantValue;

        if (blank($data['internal_reference'] ?? null) && method_exists($this, 'nextVariantReference')) {
            $data['internal_reference'] = $this->nextVariantReference($parent);
        }

        $data['product_category_id'] = $data['product_category_id'] ?? $parent->product_category_id;
        $data['inventory_unit_id'] = $data['inventory_unit_id'] ?? $parent->inventory_unit_id;
        $data['product_type'] = $data['product_type'] ?? $parent->product_type ?? 'stockable';
        $data['tracking'] = $data['tracking'] ?? $parent->tracking ?? 'none';
        $data['costing_method'] = $data['costing_method'] ?? $parent->costing_method ?? 'average';
        $data['invoice_policy'] = $data['invoice_policy']
            ?? $parent->invoice_policy
            ?? DB::table('products')->whereNotNull('invoice_policy')->value('invoice_policy')
            ?? 'delivered_quantities';
        $data['sat_unit_code'] = $data['sat_unit_code'] ?? $parent->sat_unit_code ?? 'H87';

        if (empty($data['image_path'])) {
            $data['image_path'] = $parent->image_path;
        }

        $data['can_be_sold'] = $data['can_be_sold'] ?? true;
        $data['can_be_purchased'] = $data['can_be_purchased'] ?? true;
        $data['available_in_pos'] = $data['available_in_pos'] ?? true;
        $data['is_active'] = $data['is_active'] ?? true;

        $extra = $data['extra_attributes'] ?? [];

        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($extra)) {
            $extra = [];
        }

        $extra['import_source'] = $extra['import_source'] ?? 'manual_variant';
        $extra['parent_product_id'] = $parent->id;
        $extra['variant_group'] = $variantGroup;
        $extra['variant_value'] = $variantValue;

        $data['extra_attributes'] = $extra;

        return $data;
    }


    protected function fillRequiredProductColumnsForCreate(array $data): array
    {
        $parent = $this->getVariantParentFromRequest();
        $parentAttributes = $parent ? $parent->getAttributes() : [];

        $requiredColumns = DB::select("
            select column_name, data_type, udt_name, column_default
            from information_schema.columns
            where table_schema = current_schema()
              and table_name = 'products'
              and is_nullable = 'NO'
            order by ordinal_position
        ");

        $skip = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($requiredColumns as $columnInfo) {
            $column = $columnInfo->column_name;

            if (in_array($column, $skip, true)) {
                continue;
            }

            if (array_key_exists($column, $data) && $data[$column] !== null && $data[$column] !== '') {
                continue;
            }

            if (array_key_exists($column, $parentAttributes) && $parentAttributes[$column] !== null && $parentAttributes[$column] !== '') {
                $data[$column] = $parentAttributes[$column];
                continue;
            }

            $data[$column] = $this->defaultProductValueForColumn(
                $column,
                (string) $columnInfo->data_type,
                (string) $columnInfo->udt_name,
                $data
            );
        }

        return $data;
    }

    protected function defaultProductValueForColumn(string $column, string $dataType, string $udtName, array $data): mixed
    {
        return match ($column) {
            'company_id' => $data['company_id'] ?? 1,
            'name' => $data['name'] ?? 'Producto',
            'product_type' => $data['product_type'] ?? 'stockable',
            'tracking' => $data['tracking'] ?? 'none',
            'costing_method' => $data['costing_method'] ?? 'average',
            'invoice_policy' => $data['invoice_policy'] ?? 'delivered_quantities',
            'internal_reference' => $data['internal_reference'] ?? ('VAR-' . now()->format('YmdHis')),
            'sat_unit_code' => $data['sat_unit_code'] ?? 'H87',

            'can_be_sold',
            'can_be_purchased',
            'available_in_pos',
            'is_active' => true,

            'is_variant',
            'has_variants' => false,

            'weight',
            'volume',
            'sale_price',
            'standard_cost',
            'purchase_price',
            'last_purchase_cost' => 0,

            default => $this->defaultValueByDatabaseType($dataType, $udtName),
        };
    }

    protected function defaultValueByDatabaseType(string $dataType, string $udtName): mixed
    {
        if (str_contains($dataType, 'boolean') || $udtName === 'bool') {
            return false;
        }

        if (
            str_contains($dataType, 'integer') ||
            str_contains($dataType, 'numeric') ||
            str_contains($dataType, 'decimal') ||
            str_contains($dataType, 'double') ||
            str_contains($dataType, 'real') ||
            in_array($udtName, ['int2', 'int4', 'int8', 'float4', 'float8', 'numeric'], true)
        ) {
            return 0;
        }

        if (str_contains($dataType, 'timestamp') || str_contains($dataType, 'date')) {
            return now();
        }

        if (str_contains($dataType, 'json')) {
            return [];
        }

        return '';
    }

protected function mergeFormStateForVariant(array $data): array
    {
        $state = [];

        try {
            $rawState = $this->form->getRawState();

            if ($rawState instanceof \Illuminate\Support\Collection) {
                $state = $rawState->toArray();
            } elseif (is_array($rawState)) {
                $state = $rawState;
            }
        } catch (\Throwable $e) {
            $state = [];
        }

        if (property_exists($this, 'data') && is_array($this->data)) {
            $state = array_replace_recursive($state, $this->data);
        }

        foreach ([
            'variant_group',
            'variant_value',
            'variant_name',
            'internal_reference',
            'name',
            'sale_price',
            'standard_cost',
            'purchase_price',
            'image_path',
        ] as $key) {
            if (blank($data[$key] ?? null) && filled($state[$key] ?? null)) {
                $data[$key] = $state[$key];
            }
        }

        return $data;
    }



protected function prepareVariantDataForCreate(array $data, Product $parent): array
    {
        // prepare_variant_data_v7
        $variantGroup = trim((string) ($data['variant_group'] ?? 'Color'));
        $variantValue = trim((string) ($data['variant_value'] ?? ''));

        if ($variantGroup === '') {
            $variantGroup = 'Color';
        }

        if ($variantValue === '') {
            throw ValidationException::withMessages([
                'variant_value' => 'Captura el valor de la variante antes de guardar.',
            ]);
        }

        $data['company_id'] = $parent->company_id;
        $data['parent_product_id'] = $parent->id;
        $data['is_variant'] = true;
        $data['has_variants'] = false;

        $data['variant_group'] = $variantGroup;
        $data['variant_value'] = $variantValue;
        $data['variant_name'] = $data['variant_name'] ?: $variantValue;
        $data['variant_signature'] = Str::slug($variantGroup . ':' . $variantValue);

        $data['name'] = trim((string) $parent->name) . ' - ' . $variantValue;

        if (blank($data['internal_reference'] ?? null) && method_exists($this, 'nextVariantReference')) {
            $data['internal_reference'] = $this->nextVariantReference($parent);
        }

        $data['product_category_id'] = $data['product_category_id'] ?? $parent->product_category_id;
        $data['inventory_unit_id'] = $data['inventory_unit_id'] ?? $parent->inventory_unit_id;
        $data['product_type'] = $data['product_type'] ?? $parent->product_type ?? 'stockable';
        $data['tracking'] = $data['tracking'] ?? $parent->tracking ?? 'none';
        $data['costing_method'] = $data['costing_method'] ?? $parent->costing_method ?? 'average';
        $data['invoice_policy'] = $data['invoice_policy']
            ?? $parent->invoice_policy
            ?? DB::table('products')->whereNotNull('invoice_policy')->value('invoice_policy')
            ?? 'delivered_quantities';
        $data['sat_unit_code'] = $data['sat_unit_code'] ?? $parent->sat_unit_code ?? 'H87';

        if (blank($data['image_path'] ?? null)) {
            $data['image_path'] = $parent->image_path;
        }

        $data['can_be_sold'] = $data['can_be_sold'] ?? true;
        $data['can_be_purchased'] = $data['can_be_purchased'] ?? true;
        $data['available_in_pos'] = $data['available_in_pos'] ?? true;
        $data['is_active'] = $data['is_active'] ?? true;

        $extra = $data['extra_attributes'] ?? [];

        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($extra)) {
            $extra = [];
        }

        $extra['import_source'] = $extra['import_source'] ?? 'manual_variant';
        $extra['parent_product_id'] = $parent->id;
        $extra['variant_group'] = $variantGroup;
        $extra['variant_value'] = $variantValue;

        $data['extra_attributes'] = $extra;

        return $data;
    }




    protected function syncProductTaxRatesFromForm(): void
    {
        $record = $this->record ?? null;

        if (! $record || ! $record->exists || ! \Illuminate\Support\Facades\Schema::hasTable('product_tax_rates')) {
            return;
        }

        $state = [];

        try {
            $rawState = $this->form->getRawState();

            if ($rawState instanceof \Illuminate\Support\Collection) {
                $state = $rawState->toArray();
            } elseif (is_array($rawState)) {
                $state = $rawState;
            }
        } catch (\Throwable $e) {
            $state = [];
        }

        if (property_exists($this, 'data') && is_array($this->data)) {
            $state = array_replace_recursive($state, $this->data);
        }

        $this->syncProductTaxRateUsage('sale', $state['sale_tax_rate_ids'] ?? []);
        $this->syncProductTaxRateUsage('purchase', $state['purchase_tax_rate_ids'] ?? []);
    }

    protected function syncProductTaxRateUsage(string $usageType, mixed $taxRateIds): void
    {
        $record = $this->record ?? null;

        if (! $record || ! $record->exists) {
            return;
        }

        if (! is_array($taxRateIds)) {
            $taxRateIds = filled($taxRateIds) ? [$taxRateIds] : [];
        }

        $taxRateIds = collect($taxRateIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        \Illuminate\Support\Facades\DB::table('product_tax_rates')
            ->where('product_id', $record->id)
            ->where('usage_type', $usageType)
            ->delete();

        foreach ($taxRateIds as $taxRateId) {
            \Illuminate\Support\Facades\DB::table('product_tax_rates')->insert([
                'company_id' => $record->company_id,
                'product_id' => $record->id,
                'tax_rate_id' => $taxRateId,
                'usage_type' => $usageType,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

}
