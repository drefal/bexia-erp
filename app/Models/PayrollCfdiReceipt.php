<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCfdiReceipt extends Model
{
    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'payroll_run_line_id',
        'employee_id',
        'status',
        'cfdi_version',
        'payroll_complement_version',
        'series',
        'folio',
        'uuid',
        'pac_provider',
        'pac_test_env',
        'pac_request_id',
        'pac_error_message',
        'xml_path',
        'pdf_path',
        'validated_at',
        'stamped_at',
        'cancelled_at',
        'issuer_snapshot',
        'employee_snapshot',
        'contract_snapshot',
        'totals_snapshot',
        'validation_errors',
        'metadata',
    ];

    protected $casts = [
        'pac_test_env' => 'boolean',
        'validated_at' => 'datetime',
        'stamped_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'issuer_snapshot' => 'array',
        'employee_snapshot' => 'array',
        'contract_snapshot' => 'array',
        'totals_snapshot' => 'array',
        'validation_errors' => 'array',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Borrador',
            'validated' => 'Validado',
            'stamping' => 'Timbrando',
            'stamped' => 'Timbrado',
            'cancelled' => 'Cancelado',
            'error' => 'Error',
            default => $this->status ?: 'Sin estado',
        };
    }
}
