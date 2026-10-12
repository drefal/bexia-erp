<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollConcept extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'category',
        'source',
        'unit',
        'sat_key',
        'cfdi_key',
        'is_active',
        'sort_order',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'is_taxable',
        'reduces_taxable_base',
        'taxable_amount_default',
        'exempt_amount_default',
];

    protected $casts = [
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
        'reduces_taxable_base' => 'boolean',
        'taxable_amount_default' => 'decimal:2',
        'exempt_amount_default' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function runLineConcepts()
    {
        return $this->hasMany(\App\Models\PayrollRunLineConcept::class);
    }

    public function payrollGroups()
    {
        return $this->belongsToMany(
            \App\Models\PayrollGroup::class,
            'payroll_concept_payroll_group'
        )
            ->withTimestamps()
            ->where(
                'payroll_groups.company_id',
                $this->company_id
            );
    }

    public static function typeOptions(): array
    {
        return [
            'perception' => 'Percepción',
            'deduction' => 'Deducción',
            'informational' => 'Informativo',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'base_salary' => 'Sueldo base',
            'overtime' => 'Horas extra',
            'attendance' => 'Asistencia',
            'incident' => 'Incidencia',
            'manual' => 'Manual',
            'tax' => 'Impuestos',
            'other' => 'Otro',
        ];
    }

    public static function sourceOptions(): array
    {
        return [
            'system' => 'Sistema',
            'policy' => 'Política',
            'incident' => 'Incidencia',
            'manual' => 'Manual',
        ];
    }

    public static function unitOptions(): array
    {
        return [
            'amount' => 'Importe',
            'days' => 'Días',
            'hours' => 'Horas',
            'minutes' => 'Minutos',
            'units' => 'Unidades',
        ];
    }

    public static function defaults(): array
    {
        return [
            [
                'code' => 'SUELDO_BASE',
                'name' => 'Sueldo base',
                'type' => 'perception',
                'category' => 'base_salary',
                'source' => 'system',
                'unit' => 'days',
                'sort_order' => 10,
            ],
            [
                'code' => 'HORAS_EXTRA',
                'name' => 'Horas extra',
                'type' => 'perception',
                'category' => 'overtime',
                'source' => 'system',
                'unit' => 'hours',
                'sort_order' => 20,
            ],
            [
                'code' => 'INCIDENCIAS_PERCEPCION',
                'name' => 'Percepciones por incidencias',
                'type' => 'perception',
                'category' => 'incident',
                'source' => 'incident',
                'unit' => 'amount',
                'sort_order' => 30,
            ],
            [
                'code' => 'INCIDENCIAS_DEDUCCION',
                'name' => 'Deducciones por incidencias',
                'type' => 'deduction',
                'category' => 'incident',
                'source' => 'incident',
                'unit' => 'amount',
                'sort_order' => 110,
            ],
            [
                'code' => 'POLITICA_RETARDO',
                'name' => 'Descuento por retardo',
                'type' => 'deduction',
                'category' => 'attendance',
                'source' => 'policy',
                'unit' => 'minutes',
                'sort_order' => 120,
            ],
            [
                'code' => 'POLITICA_SALIDA_TEMPRANA',
                'name' => 'Descuento por salida temprana',
                'type' => 'deduction',
                'category' => 'attendance',
                'source' => 'policy',
                'unit' => 'minutes',
                'sort_order' => 130,
            ],
            [
                'code' => 'POLITICA_FALTA',
                'name' => 'Descuento por falta',
                'type' => 'deduction',
                'category' => 'attendance',
                'source' => 'policy',
                'unit' => 'days',
                'sort_order' => 140,
            ],
        ];
    }
}
