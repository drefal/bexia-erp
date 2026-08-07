<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * BEXIA_V5_83_P12_PORTABLE_COMPANY_SLUG
 *
 * Homologación de atributos de producto V5.83.P12.
 *
 * El artefacto NO transporta IDs de empresa entre
 * ambientes. Cada empresa se resuelve mediante companies.slug.
 *
 * Atributos y valores se resuelven mediante claves naturales.
 *
 * No se hacen equivalencias semánticas.
 */
return new class extends Migration
{
    private function normalize(
        ?string $value
    ): string {
        $value = trim(
            (string) $value
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return mb_strtolower(
            $value,
            'UTF-8'
        );
    }


    private function asciiNormalize(
        ?string $value
    ): string {
        return mb_strtolower(
            Str::ascii(
                $this->normalize(
                    $value
                )
            ),
            'UTF-8'
        );
    }


    private function catalog(): array
    {
        $path = database_path(
            'migrations/data/'
            . 'v5_83_p12_product_attribute_catalog.json'
        );

        if (! is_file($path)) {
            throw new RuntimeException(
                'No existe catálogo V5.83.P12.'
            );
        }

        $data = json_decode(
            file_get_contents(
                $path
            ),
            true
        );

        if (
            ! is_array($data)
            ||
            ! isset(
                $data['companies'],
                $data['attributes']
            )
            ||
            ! is_array(
                $data['companies']
            )
            ||
            ! is_array(
                $data['attributes']
            )
        ) {
            throw new RuntimeException(
                'Catálogo V5.83.P12 inválido.'
            );
        }

        return $data;
    }


    private function resolveCompany(
        string $slug
    ): object {
        $normalized =
            $this->normalize(
                $slug
            );

        if ($normalized === '') {
            throw new RuntimeException(
                'Slug de empresa vacío.'
            );
        }

        $rows = DB::table(
            'companies'
        )
            ->whereRaw(
                'LOWER(TRIM(slug)) = ?',
                [$normalized]
            )
            ->get([
                'id',
                'name',
                'slug',
            ]);

        if ($rows->count() !== 1) {
            throw new RuntimeException(
                'Empresa no resoluble de forma única por slug: '
                . $slug
            );
        }

        return $rows->first();
    }


    public function up(): void
    {
        $catalog =
            $this->catalog();

        DB::transaction(
            function () use (
                $catalog
            ): void {

                /*
                 * ==============================================
                 * 1) Resolver slugs a IDs LOCALES
                 * ==============================================
                 */

                $companyIdBySlug = [];
                $companySlugById = [];

                foreach (
                    $catalog['companies']
                    as $companySlug
                ) {

                    $companySlug =
                        trim(
                            (string) $companySlug
                        );

                    $company =
                        $this->resolveCompany(
                            $companySlug
                        );

                    $normalizedSlug =
                        $this->normalize(
                            $companySlug
                        );

                    $companyIdBySlug[
                        $normalizedSlug
                    ] =
                        (int) $company->id;

                    $companySlugById[
                        (int) $company->id
                    ] =
                        (string) $company->slug;
                }


                /*
                 * ==============================================
                 * 2) Homologar catálogo
                 * ==============================================
                 */

                foreach (
                    $catalog['attributes']
                    as $definition
                ) {

                    $companySlug =
                        trim(
                            (string) (
                                $definition[
                                    'company_slug'
                                ]
                                ?? ''
                            )
                        );

                    $slugKey =
                        $this->normalize(
                            $companySlug
                        );

                    if (
                        $slugKey === ''
                        ||
                        ! isset(
                            $companyIdBySlug[
                                $slugKey
                            ]
                        )
                    ) {
                        throw new RuntimeException(
                            'Atributo con empresa no resoluble: '
                            . $companySlug
                        );
                    }

                    $companyId =
                        $companyIdBySlug[
                            $slugKey
                        ];

                    $code =
                        trim(
                            (string) $definition[
                                'code'
                            ]
                        );

                    $name =
                        trim(
                            (string) $definition[
                                'name'
                            ]
                        );


                    /*
                     * Primero por código natural.
                     */
                    $byCode =
                        DB::table(
                            'product_attributes'
                        )
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->whereRaw(
                                'LOWER(TRIM(code)) = ?',
                                [
                                    $this->normalize(
                                        $code
                                    ),
                                ]
                            )
                            ->get();


                    if (
                        $byCode->count()
                        > 1
                    ) {
                        throw new RuntimeException(
                            'Atributo ambiguo por código: '
                            . $companySlug
                            . '/'
                            . $code
                        );
                    }


                    $attribute =
                        $byCode->first();


                    /*
                     * Fallback por nombre.
                     */
                    if (! $attribute) {

                        $byName =
                            DB::table(
                                'product_attributes'
                            )
                                ->where(
                                    'company_id',
                                    $companyId
                                )
                                ->whereRaw(
                                    'LOWER(TRIM(name)) = ?',
                                    [
                                        $this->normalize(
                                            $name
                                        ),
                                    ]
                                )
                                ->get();


                        if (
                            $byName->count()
                            > 1
                        ) {
                            throw new RuntimeException(
                                'Atributo ambiguo por nombre: '
                                . $companySlug
                                . '/'
                                . $name
                            );
                        }


                        $attribute =
                            $byName->first();
                    }


                    $attributeData = [
                        'company_id' =>
                            $companyId,

                        'code' =>
                            $code,

                        'name' =>
                            $name,

                        'is_variant' =>
                            (bool) (
                                $definition[
                                    'is_variant'
                                ]
                                ?? false
                            ),

                        'is_active' =>
                            (bool) (
                                $definition[
                                    'is_active'
                                ]
                                ?? true
                            ),

                        'is_system' =>
                            (bool) (
                                $definition[
                                    'is_system'
                                ]
                                ?? false
                            ),

                        'sort_order' =>
                            (int) (
                                $definition[
                                    'sort_order'
                                ]
                                ?? 0
                            ),

                        'updated_at' =>
                            now(),
                    ];


                    if ($attribute) {

                        DB::table(
                            'product_attributes'
                        )
                            ->where(
                                'id',
                                $attribute->id
                            )
                            ->update(
                                $attributeData
                            );

                        $attributeId =
                            (int) $attribute->id;

                    } else {

                        $attributeId =
                            DB::table(
                                'product_attributes'
                            )
                                ->insertGetId(
                                    array_merge(
                                        $attributeData,
                                        [
                                            'created_at' =>
                                                now(),
                                        ]
                                    )
                                );
                    }


                    /*
                     * Valores.
                     */
                    foreach (
                        $definition[
                            'values'
                        ]
                        ?? []
                        as $valueDefinition
                    ) {

                        $valueCode =
                            trim(
                                (string) (
                                    $valueDefinition[
                                        'code'
                                    ]
                                    ?? ''
                                )
                            );

                        $valueName =
                            trim(
                                (string) (
                                    $valueDefinition[
                                        'name'
                                    ]
                                    ?? ''
                                )
                            );


                        $valueByCode =
                            DB::table(
                                'product_attribute_values'
                            )
                                ->where(
                                    'company_id',
                                    $companyId
                                )
                                ->where(
                                    'product_attribute_id',
                                    $attributeId
                                )
                                ->whereRaw(
                                    'LOWER(TRIM(code)) = ?',
                                    [
                                        $this->normalize(
                                            $valueCode
                                        ),
                                    ]
                                )
                                ->get();


                        if (
                            $valueByCode->count()
                            > 1
                        ) {
                            throw new RuntimeException(
                                'Valor ambiguo por código: '
                                . $companySlug
                                . '/'
                                . $name
                                . '/'
                                . $valueCode
                            );
                        }


                        $value =
                            $valueByCode->first();


                        if (! $value) {

                            $valueByName =
                                DB::table(
                                    'product_attribute_values'
                                )
                                    ->where(
                                        'company_id',
                                        $companyId
                                    )
                                    ->where(
                                        'product_attribute_id',
                                        $attributeId
                                    )
                                    ->whereRaw(
                                        'LOWER(TRIM(name)) = ?',
                                        [
                                            $this->normalize(
                                                $valueName
                                            ),
                                        ]
                                    )
                                    ->get();


                            if (
                                $valueByName->count()
                                > 1
                            ) {
                                throw new RuntimeException(
                                    'Valor ambiguo por nombre: '
                                    . $companySlug
                                    . '/'
                                    . $name
                                    . '/'
                                    . $valueName
                                );
                            }


                            $value =
                                $valueByName->first();
                        }


                        $valueData = [
                            'company_id' =>
                                $companyId,

                            'product_attribute_id' =>
                                $attributeId,

                            'code' =>
                                $valueCode,

                            'name' =>
                                $valueName,

                            'is_active' =>
                                (bool) (
                                    $valueDefinition[
                                        'is_active'
                                    ]
                                    ?? true
                                ),

                            'sort_order' =>
                                (int) (
                                    $valueDefinition[
                                        'sort_order'
                                    ]
                                    ?? 0
                                ),

                            'updated_at' =>
                                now(),
                        ];


                        if ($value) {

                            DB::table(
                                'product_attribute_values'
                            )
                                ->where(
                                    'id',
                                    $value->id
                                )
                                ->update(
                                    $valueData
                                );

                        } else {

                            DB::table(
                                'product_attribute_values'
                            )
                                ->insert(
                                    array_merge(
                                        $valueData,
                                        [
                                            'created_at' =>
                                                now(),
                                        ]
                                    )
                                );
                        }
                    }
                }


                /*
                 * ==============================================
                 * 3) Mapas canónicos locales
                 * ==============================================
                 */

                $companyIds =
                    array_values(
                        $companyIdBySlug
                    );

                $attributeMaps = [];
                $valueMaps = [];


                foreach (
                    $companyIds
                    as $companyId
                ) {

                    $attributes =
                        DB::table(
                            'product_attributes'
                        )
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->where(
                                'is_variant',
                                true
                            )
                            ->get();


                    foreach (
                        $attributes
                        as $attribute
                    ) {

                        $canonicalName =
                            (string) $attribute->name;


                        $attributeMaps[
                            $companyId
                        ][
                            $this->normalize(
                                $attribute->name
                            )
                        ] =
                            $canonicalName;


                        $attributeMaps[
                            $companyId
                        ][
                            $this->normalize(
                                $attribute->code
                            )
                        ] =
                            $canonicalName;


                        $values =
                            DB::table(
                                'product_attribute_values'
                            )
                                ->where(
                                    'company_id',
                                    $companyId
                                )
                                ->where(
                                    'product_attribute_id',
                                    $attribute->id
                                )
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->get();


                        foreach (
                            $values
                            as $value
                        ) {

                            $valueMaps[
                                $companyId
                            ][
                                $canonicalName
                            ][
                                $this->normalize(
                                    $value->name
                                )
                            ] =
                                (string) $value->name;
                        }
                    }
                }


                /*
                 * Únicamente alias ortográficos aprobados.
                 */
                $aliases = [
                    'aplicacion' =>
                        'Aplicación',

                    'froma' =>
                        'Forma',

                    'numero' =>
                        'Número',

                    'diametro' =>
                        'Diámetro',

                    'metalicos' =>
                        'Metálicos',
                ];


                /*
                 * ==============================================
                 * 4) Homologar variantes históricas
                 * ==============================================
                 */

                DB::table('products')
                    ->whereIn(
                        'company_id',
                        $companyIds
                    )
                    ->where(
                        'is_variant',
                        true
                    )
                    ->orderBy('id')
                    ->chunkById(
                        250,
                        function (
                            $variants
                        ) use (
                            $attributeMaps,
                            $valueMaps,
                            $aliases,
                            $companySlugById
                        ): void {

                            foreach (
                                $variants
                                as $variant
                            ) {

                                $companyId =
                                    (int) $variant->company_id;

                                $companySlug =
                                    $this->normalize(
                                        $companySlugById[
                                            $companyId
                                        ]
                                        ?? ''
                                    );

                                $group =
                                    trim(
                                        (string) $variant->variant_group
                                    );

                                $value =
                                    trim(
                                        (string) $variant->variant_value
                                    );


                                /*
                                 * BEXIA_V5_83_P12_SCOPE_GRUPOL7_ONLY
                                 *
                                 * La homologacion masiva P12 esta
                                 * limitada exclusivamente a las
                                 * empresas declaradas en el catalogo
                                 * versionado. Para esta migracion el
                                 * unico slug permitido es GRUPOL7.
                                 *
                                 * No existen reglas especiales para
                                 * empresas fuera de ese alcance.
                                 */

                                if ($group === '') {
                                    continue;
                                }


                                $canonicalGroup =
                                    $attributeMaps[
                                        $companyId
                                    ][
                                        $this->normalize(
                                            $group
                                        )
                                    ]
                                    ?? null;


                                if (! $canonicalGroup) {

                                    $alias =
                                        $aliases[
                                            $this->asciiNormalize(
                                                $group
                                            )
                                        ]
                                        ?? null;


                                    if (
                                        $alias
                                        &&
                                        isset(
                                            $attributeMaps[
                                                $companyId
                                            ][
                                                $this->normalize(
                                                    $alias
                                                )
                                            ]
                                        )
                                    ) {

                                        $canonicalGroup =
                                            $attributeMaps[
                                                $companyId
                                            ][
                                                $this->normalize(
                                                    $alias
                                                )
                                            ];
                                    }
                                }


                                if (! $canonicalGroup) {
                                    continue;
                                }


                                $canonicalValue =
                                    null;


                                if ($value !== '') {

                                    $canonicalValue =
                                        $valueMaps[
                                            $companyId
                                        ][
                                            $canonicalGroup
                                        ][
                                            $this->normalize(
                                                $value
                                            )
                                        ]
                                        ?? null;


                                    /*
                                     * Sin coincidencia inequívoca,
                                     * no modificar.
                                     */
                                    if (! $canonicalValue) {
                                        continue;
                                    }
                                }


                                $updates = [];


                                if (
                                    $group
                                    !==
                                    $canonicalGroup
                                ) {
                                    $updates[
                                        'variant_group'
                                    ] =
                                        $canonicalGroup;
                                }


                                if (
                                    $value !== ''
                                    &&
                                    $value
                                    !==
                                    $canonicalValue
                                ) {
                                    $updates[
                                        'variant_value'
                                    ] =
                                        $canonicalValue;
                                }


                                /*
                                 * variant_name sólo se limpia
                                 * si duplica exactamente el
                                 * valor canónico.
                                 */
                                $variantName =
                                    trim(
                                        (string) $variant->variant_name
                                    );

                                $finalValue =
                                    $canonicalValue
                                    ?? $value;


                                if (
                                    $finalValue !== ''
                                    &&
                                    $variantName !== ''
                                    &&
                                    $this->normalize(
                                        $variantName
                                    )
                                    ===
                                    $this->normalize(
                                        $finalValue
                                    )
                                ) {

                                    $updates[
                                        'variant_name'
                                    ] =
                                        null;
                                }


                                if (! empty($updates)) {

                                    $updates[
                                        'updated_at'
                                    ] =
                                        now();

                                    DB::table(
                                        'products'
                                    )
                                        ->where(
                                            'id',
                                            $variant->id
                                        )
                                        ->update(
                                            $updates
                                        );
                                }
                            }
                        },
                        'id'
                    );
            }
        );
    }


    /*
     * No destructivo.
     *
     * Los atributos pueden comenzar a ser utilizados por
     * productos creados después del despliegue.
     */
    public function down(): void
    {
        //
    }
};
