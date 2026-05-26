<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmployeeTermination extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'employee_contract_id',
        'termination_number',
        'termination_type',
        'status',
        'termination_date',
        'last_working_day',
        'notice_date',
        'rehire_eligible',
        'settlement_amount',
        'currency',
        'file_path',
        'file_original_name',
        'reason',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'completed_by_user_id',
        'completed_at',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'last_working_day' => 'date',
        'notice_date' => 'date',
        'rehire_eligible' => 'boolean',
        'settlement_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function contract()
    {
        return $this->belongsTo(\App\Models\EmployeeContract::class, 'employee_contract_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'completed_by_user_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        return asset('storage/' . ltrim($this->file_path, '/'));
    }
}
