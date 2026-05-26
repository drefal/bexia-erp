<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'hr_document_type_id',
        'name',
        'document_number',
        'status',
        'issued_at',
        'expires_at',
        'file_path',
        'file_original_name',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function documentType()
    {
        return $this->belongsTo(\App\Models\HrDocumentType::class, 'hr_document_type_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
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

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
