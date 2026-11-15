<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $companyId = 3;

            if (! DB::table('companies')
                ->where('id', $companyId)
                ->exists()) {
                throw new RuntimeException(
                    'GLB4_F9_COMPANY_3_MISSING'
                );
            }

            $parents = DB::table('product_categories')
                ->where('company_id', $companyId)
                ->where('name', 'MAGICTOYS')
                ->where('code', 'MT')
                ->lockForUpdate()
                ->get();

            if ($parents->count() !== 1) {
                throw new RuntimeException(
                    'GLB4_F9_MAGICTOYS_PARENT_NOT_UNIQUE'
                );
            }

            $parent = $parents->first();

            if ($parent->parent_id !== null) {
                throw new RuntimeException(
                    'GLB4_F9_MAGICTOYS_PARENT_CHANGED'
                );
            }

            $existing = DB::table('product_categories')
                ->where('company_id', $companyId)
                ->where(function ($query): void {
                    $query
                        ->where('code', 'GLB-PERS')
                        ->orWhere(
                            'name',
                            'Globos Personalizados'
                        );
                })
                ->lockForUpdate()
                ->get();

            if ($existing->count() > 1) {
                throw new RuntimeException(
                    'GLB4_F9_DUPLICATE_BALLOON_CATEGORY'
                );
            }

            if ($existing->count() === 1) {
                $category = $existing->first();

                if (
                    $category->code !== 'GLB-PERS'
                    || $category->name !== 'Globos Personalizados'
                    || (int) $category->parent_id
                        !== (int) $parent->id
                ) {
                    throw new RuntimeException(
                        'GLB4_F9_EXISTING_CATEGORY_CONFLICT'
                    );
                }

                return;
            }

            $newId = DB::table('product_categories')
                ->insertGetId([
                    'company_id' => $companyId,
                    'parent_id' => (int) $parent->id,
                    'code' => 'GLB-PERS',
                    'name' => 'Globos Personalizados',
                    'full_path' =>
                        'MAGICTOYS / Globos Personalizados',
                    'level' => 1,
                    'sort_order' => 0,
                    'is_active' => true,
                    'costing_method' => 'inherit',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if (! $newId) {
                throw new RuntimeException(
                    'GLB4_F9_CATEGORY_INSERT_FAILED'
                );
            }
        });
    }

    public function down(): void
    {
        // No eliminar una categoria que pueda contener
        // productos u operaciones comerciales.
        // Reversion manual y auditada si es necesaria.
    }
};
