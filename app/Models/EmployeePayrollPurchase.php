<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeePayrollPurchase extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'number',
        'purchase_date',
        'frequency',
        'installments_count',
        'first_deduction_date',
        'subtotal',
        'tax_total',
        'total_amount',
        'status',
        'notes',
        'confirmed_at',
        'confirmed_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'first_deduction_date' => 'date',
        'installments_count' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $purchase): void {
            if (blank($purchase->status)) {
                $purchase->status = 'draft';
            }

            if (! $purchase->created_by_user_id && auth()->check()) {
                $purchase->created_by_user_id = auth()->id();
            }

            if (! $purchase->updated_by_user_id && auth()->check()) {
                $purchase->updated_by_user_id = auth()->id();
            }
        });

        static::created(function (self $purchase): void {
            if (blank($purchase->number)) {
                $purchase->forceFill([
                    'number' => 'CN-' . str_pad((string) $purchase->getKey(), 7, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });

        static::updating(function (self $purchase): void {
            if (auth()->check()) {
                $purchase->updated_by_user_id = auth()->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EmployeePayrollPurchaseLine::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(EmployeePayrollPurchaseInstallment::class);
    }

    public function deduction(): HasOne
    {
        return $this->hasOne(EmployeePayrollDeduction::class, 'employee_payroll_purchase_id');
    }

    public static function frequencyOptions(): array
    {
        return [
            'weekly' => 'Semanal',
            'biweekly' => 'Quincenal (cada 15 días)',
            'monthly' => 'Mensual',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'confirmed' => 'Confirmada',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
        ];
    }
}
