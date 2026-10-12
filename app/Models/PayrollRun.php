<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_group_id',
        'calculation_mode_snapshot',
        'run_type',
        'name',
        'period_type',
        'period_start',
        'period_end',
        'payment_date',
        'status',
        'currency',
        'employees_count',
        'base_total',
        'overtime_total',
        'perceptions_total',
        'gross_total',
        'deductions_total',
        'net_total',
        'summary',
        'approval_status',
        'approval_request_id',
        'approval_requested_by_user_id',
        'approval_requested_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
        'lock_reason',
        'locked_by_user_id',
        'locked_at',
        'is_locked',
        'close_reason',
        'closed_by_user_id',
        'closed_at',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'calculated_by_user_id',
        'calculated_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'closed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'approved_at' => 'datetime',
        'approval_requested_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'employees_count' => 'integer',
        'base_total' => 'decimal:2',
        'overtime_total' => 'decimal:2',
        'perceptions_total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'net_total' => 'decimal:2',
        'summary' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            /*
             * El modo pertenece al grupo y queda congelado
             * al momento de crear la corrida.
             *
             * Esto aplica también a creación por servicio,
             * consola, seed, importación, etc.; no sólo Filament.
             */
            if ($run->payroll_group_id) {
                $groupQuery = \App\Models\PayrollGroup::query()
                    ->whereKey($run->payroll_group_id);

                if ($run->company_id) {
                    $groupQuery->where(
                        'company_id',
                        $run->company_id
                    );
                }

                $group = $groupQuery->first();

                if (! $group) {
                    throw new \RuntimeException(
                        'El grupo de nómina no existe '
                        . 'o no pertenece a la empresa.'
                    );
                }

                $mode = (string) (
                    $group->calculation_mode
                    ?: 'operational'
                );

                $run->calculation_mode_snapshot =
                    array_key_exists(
                        $mode,
                        static::calculationModeOptions()
                    )
                        ? $mode
                        : 'operational';
            } else {
                /*
                 * Compatibilidad con corridas históricas
                 * que pueden no tener grupo.
                 */
                $mode = (string) (
                    $run->calculation_mode_snapshot
                    ?: 'operational'
                );

                $run->calculation_mode_snapshot =
                    array_key_exists(
                        $mode,
                        static::calculationModeOptions()
                    )
                        ? $mode
                        : 'operational';
            }

            $runType = (string) (
                $run->run_type
                ?: 'ordinary'
            );

            $run->run_type = array_key_exists(
                $runType,
                static::runTypeOptions()
            )
                ? $runType
                : 'ordinary';
        });

        /*
         * Estos campos son snapshots históricos.
         *
         * Una corrida existente no puede cambiar de grupo,
         * modo ni tipo mediante un save programático.
         */
        static::updating(function (self $run): void {
            if ($run->exists) {
                $run->payroll_group_id =
                    $run->getOriginal(
                        'payroll_group_id'
                    );

                $run->calculation_mode_snapshot =
                    $run->getOriginal(
                        'calculation_mode_snapshot'
                    )
                    ?: 'operational';

                $run->run_type =
                    $run->getOriginal('run_type')
                    ?: 'ordinary';
            }
        });
    }

    public function payrollGroup()
    {
        return $this->belongsTo(\App\Models\PayrollGroup::class);
    }

    public function lines()
    {
        return $this->hasMany(\App\Models\PayrollRunLine::class);
    }

    public function versions()
    {
        return $this->hasMany(
            \App\Models\PayrollRunVersion::class
        )->orderByDesc('version_number');
    }

    public function perceptionApplications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollPerceptionApplication::class);
    }

    public function deductionApplications()
    {
        return $this->hasMany(\App\Models\EmployeePayrollDeductionApplication::class);
    }

    public function lineConcepts()
    {
        return $this->hasMany(\App\Models\PayrollRunLineConcept::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }

    public function calculatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'calculated_by_user_id');
    }

    public static function runTypeOptions(): array
    {
        return [
            'ordinary' => 'Ordinaria',
            'extraordinary' => 'Extraordinaria',
        ];
    }

    public static function calculationModeOptions(): array
    {
        return [
            'operational' => 'Nómina simple',
            'fiscal_preview' => 'Nómina fiscal sin timbrado',
            'fiscal_cfdi' => 'Nómina fiscal con CFDI',
        ];
    }

    public function calculationMode(): string
    {
        $mode = (string) (
            $this->calculation_mode_snapshot
            ?: 'operational'
        );

        return array_key_exists(
            $mode,
            static::calculationModeOptions()
        )
            ? $mode
            : 'operational';
    }

    public function calculationModeLabel(): string
    {
        return static::calculationModeOptions()[
            $this->calculationMode()
        ] ?? 'Nómina simple';
    }

    public function calculatesFiscalTaxes(): bool
    {
        return in_array(
            $this->calculationMode(),
            ['fiscal_preview', 'fiscal_cfdi'],
            true
        );
    }

    public function requiresSatConcepts(): bool
    {
        return $this->calculatesFiscalTaxes();
    }

    public function enablesCfdi(): bool
    {
        return $this->calculationMode()
            === 'fiscal_cfdi';
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'calculated' => 'Calculada',
            'pending_approval' => 'Pendiente de aprobación',
            'approved' => 'Aprobada',
            'closed' => 'Cerrada',
            'cancelled' => 'Cancelada',
        ];
    }

    public static function periodTypeOptions(): array
    {
        return [
            'semanal' => 'Semanal',
            'catorcenal' => 'Catorcenal',
            'quincenal' => 'Quincenal',
            'mensual' => 'Mensual',
        ];
    }
}
