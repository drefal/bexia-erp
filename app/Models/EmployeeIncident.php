<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmployeeIncident extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'employee_attendance_id',
        'hr_incident_type_id',
        'title',
        'status',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'quantity',
        'quantity_unit',
        'affects_payroll',
        'payroll_amount',
        'requires_approval',
        'approved_by_user_id',
        'approved_at',
        'attachment_path',
        'attachment_original_name',
        'description',
        'resolution_notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'quantity' => 'decimal:2',
        'payroll_amount' => 'decimal:2',
        'affects_payroll' => 'boolean',
        'requires_approval' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }


    /*
     * V5.64.16b-start
     * Asistencia origen de la incidencia.
     */
    public function attendance()
    {
        return $this->belongsTo(\App\Models\EmployeeAttendance::class, 'employee_attendance_id');
    }
    /*
     * V5.64.16b-end
     */

    public function incidentType()
    {
        return $this->belongsTo(\App\Models\HrIncidentType::class, 'hr_incident_type_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_user_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (blank($this->attachment_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->attachment_path)) {
            return null;
        }

        return asset('storage/' . ltrim($this->attachment_path, '/'));
    }
}
