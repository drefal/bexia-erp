<?php

namespace App\Support\FiscalSat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SatCatalogLabelService
{
    public static function label(string $catalog, ?string $code, bool $withCode = true): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return '-';
        }

        $description = self::findInExistingCatalogs($catalog, $code)
            ?: self::fallbackDescription($catalog, $code);

        if (! $description) {
            return $code;
        }

        return $withCode ? "{$code} - {$description}" : $description;
    }

    public static function direction(?string $direction): string
    {
        return match ($direction) {
            'issued' => 'Emitido por la empresa',
            'received' => 'Recibido por la empresa',
            default => (string) ($direction ?: '-'),
        };
    }

    public static function cfdiType(?string $type): string
    {
        return match ($type) {
            'I' => 'Ingreso',
            'E' => 'Egreso',
            'P' => 'Pago',
            'N' => 'Nómina',
            'T' => 'Traslado',
            default => (string) ($type ?: '-'),
        };
    }

    public static function taxDirection(?string $direction): string
    {
        return match ($direction) {
            'transferred' => 'Traslado',
            'withheld' => 'Retención',
            default => (string) ($direction ?: '-'),
        };
    }

    public static function tax(?string $tax): string
    {
        $tax = trim((string) $tax);

        return match ($tax) {
            '001' => 'ISR',
            '002' => 'IVA',
            '003' => 'IEPS',
            'ISR', 'IVA', 'IEPS' => $tax,
            default => $tax !== '' ? $tax : '-',
        };
    }

    public static function ratePercent($rate): string
    {
        if ($rate === null || $rate === '') {
            return '-';
        }

        $value = (float) $rate;

        if ($value <= 1) {
            $value *= 100;
        }

        return number_format($value, 2) . '%';
    }

    private static function findInExistingCatalogs(string $catalog, string $code): ?string
    {
        return Cache::remember(
            'sat_catalog_label_' . md5($catalog . '|' . $code),
            now()->addMinutes(30),
            fn () => self::lookup($catalog, $code)
        );
    }

    private static function lookup(string $catalog, string $code): ?string
    {
        $plans = self::catalogPlans($catalog);

        foreach ($plans as $plan) {
            $label = self::lookupPlan($plan, $code);

            if ($label) {
                return $label;
            }
        }

        return null;
    }

    private static function catalogPlans(string $catalog): array
    {
        return match ($catalog) {
            'product_service' => [
                ['table' => 'sat_product_service_codes'],
                ['table' => 'sat_billing_catalog_items', 'catalog_patterns' => ['ClaveProdServ', 'c_ClaveProdServ', 'producto', 'servicio']],
            ],
            'unit_code' => [
                ['table' => 'sat_unit_codes'],
                ['table' => 'sat_units'],
                ['table' => 'sat_billing_catalog_items', 'catalog_patterns' => ['ClaveUnidad', 'c_ClaveUnidad', 'unidad']],
            ],
            'payment_form' => [
                ['table' => 'sat_payment_forms'],
                ['table' => 'sat_billing_catalog_items', 'catalog_patterns' => ['FormaPago', 'c_FormaPago']],
            ],
            'payment_method' => [
                ['table' => 'sat_payment_methods'],
                ['table' => 'sat_billing_catalog_items', 'catalog_patterns' => ['MetodoPago', 'c_MetodoPago']],
            ],
            'cfdi_usage' => [
                ['table' => 'sat_cfdi_usages'],
                ['table' => 'sat_billing_catalog_items', 'catalog_patterns' => ['UsoCFDI', 'c_UsoCFDI']],
            ],
            'tax_regime' => [
                ['table' => 'sat_tax_regimes'],
                ['table' => 'sat_billing_catalog_items', 'catalog_patterns' => ['RegimenFiscal', 'c_RegimenFiscal']],
            ],
            default => [
                ['table' => 'sat_billing_catalog_items'],
            ],
        };
    }

    private static function lookupPlan(array $plan, string $code): ?string
    {
        $table = $plan['table'] ?? null;

        if (! $table || ! Schema::hasTable($table)) {
            return null;
        }

        $columns = Schema::getColumnListing($table);

        $codeColumns = array_values(array_intersect($columns, [
            'code',
            'key',
            'clave',
            'sat_code',
            'value',
            'codigo',
            'catalog_key',
            'c_clave',
            'clave_sat',
            'c_ClaveProdServ',
            'c_ClaveUnidad',
        ]));

        $labelColumns = array_values(array_intersect($columns, [
            'description',
            'descripcion',
            'name',
            'nombre',
            'label',
            'texto',
            'text',
            'description_text',
            'display_name',
        ]));

        if ($codeColumns === [] || $labelColumns === []) {
            return null;
        }

        $query = DB::table($table);

        if (! empty($plan['catalog_patterns'])) {
            $catalogColumns = array_values(array_intersect($columns, [
                'catalog',
                'catalog_name',
                'catalog_code',
                'type',
                'category',
                'group',
                'grupo',
            ]));

            if ($catalogColumns !== []) {
                $patterns = $plan['catalog_patterns'];

                $query->where(function ($q) use ($catalogColumns, $patterns) {
                    foreach ($catalogColumns as $column) {
                        foreach ($patterns as $pattern) {
                            $q->orWhere($column, 'ILIKE', '%' . $pattern . '%');
                        }
                    }
                });
            }
        }

        $query->where(function ($q) use ($codeColumns, $code) {
            foreach ($codeColumns as $column) {
                $q->orWhere($column, $code);
            }
        });

        $row = $query->first();

        if (! $row) {
            return null;
        }

        foreach ($labelColumns as $column) {
            $value = trim((string) ($row->{$column} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function fallbackDescription(string $catalog, string $code): ?string
    {
        $fallbacks = [
            'payment_form' => [
                '01' => 'Efectivo',
                '02' => 'Cheque nominativo',
                '03' => 'Transferencia electrónica de fondos',
                '04' => 'Tarjeta de crédito',
                '28' => 'Tarjeta de débito',
                '99' => 'Por definir',
            ],
            'payment_method' => [
                'PUE' => 'Pago en una sola exhibición',
                'PPD' => 'Pago en parcialidades o diferido',
            ],
            'cfdi_usage' => [
                'G01' => 'Adquisición de mercancías',
                'G02' => 'Devoluciones, descuentos o bonificaciones',
                'G03' => 'Gastos en general',
                'I01' => 'Construcciones',
                'I02' => 'Mobiliario y equipo de oficina por inversiones',
                'I03' => 'Equipo de transporte',
                'P01' => 'Por definir',
                'S01' => 'Sin efectos fiscales',
            ],
            'tax_regime' => [
                '601' => 'General de Ley Personas Morales',
                '603' => 'Personas Morales con Fines no Lucrativos',
                '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
                '606' => 'Arrendamiento',
                '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
                '621' => 'Incorporación Fiscal',
                '625' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
                '626' => 'Régimen Simplificado de Confianza',
            ],
            'unit_code' => [
                'E48' => 'Unidad de servicio',
                'H87' => 'Pieza',
                'ACT' => 'Actividad',
            ],
            'product_service' => [
                '80131502' => 'Arrendamiento de instalaciones comerciales o industriales',
            ],
        ];

        return $fallbacks[$catalog][$code] ?? null;
    }
}
