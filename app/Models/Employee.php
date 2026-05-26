<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    protected $fillable = [
        'company_id',
        'avatar_path',
        'user_id',
        'manager_employee_id',
        'coach_employee_id',
        'branch_id',

        'name',
        'employee_number',
        'position',
        'department',
        'hr_department_id',
        'hr_job_position_id',
        'hr_work_schedule_id',
        'payroll_periodicity_id',
        'payroll_employer_registration_id',

        'email',
        'phone',
        'work_mobile',

        'ssn',
        'curp',
        'rfc',
        'employee_type',

        'work_address',
        'work_timezone',
        'working_schedule',
        'flexible_hours',

        'pin_code',
        'badge_id',
        'hourly_cost',
        'fleet_card',

        'private_address',
        'private_email',
        'private_phone',
        'bank_account',
        'language',
        'distance_home_work',

        'marital_status',
        'dependent_children',

        'emergency_contact_name',
        'emergency_contact_phone',

        'nationality',
        'identification_number',
        'passport_number',
        'gender',
        'birth_date',
        'birth_place',
        'birth_country',

        'certificate_level',
        'study_field',
        'school',

        'visa_number',
        'work_permit_number',
        'visa_expiration_date',
        'work_permit_expiration_date',
        'work_permit_file',

        'active',
        'plain_pos_pin',
        'pos_active',
        'is_pos_cashier',
        'is_pos_seller',
        'pos_pin_hash',
        'notes',
    ];

    protected $casts = [
        'pos_active' => 'boolean',
        'is_pos_seller' => 'boolean',
        'is_pos_cashier' => 'boolean',
        'active' => 'boolean',
        'flexible_hours' => 'boolean',
        'birth_date' => 'date',
        'visa_expiration_date' => 'date',
        'work_permit_expiration_date' => 'date',
        'hourly_cost' => 'decimal:2',
        'distance_home_work' => 'decimal:2',
        'dependent_children' => 'integer',
        'hr_department_id' => 'integer',
        'hr_job_position_id' => 'integer',
        'hr_work_schedule_id' => 'integer',
        'payroll_periodicity_id' => 'integer',
        'payroll_employer_registration_id' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function manager()
    {
        return $this->belongsTo(\App\Models\Employee::class, 'manager_employee_id');
    }

    public function coach()
    {
        return $this->belongsTo(\App\Models\Employee::class, 'coach_employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }


    /*
     * V5.64.2c-start
     * Relaciones RRHH/Nomina para empleados.
     */
    public function hrDepartment()
    {
        return $this->belongsTo(\App\Models\HrDepartment::class, 'hr_department_id');
    }

    public function hrJobPosition()
    {
        return $this->belongsTo(\App\Models\HrJobPosition::class, 'hr_job_position_id');
    }

    public function hrWorkSchedule()
    {
        return $this->belongsTo(\App\Models\HrWorkSchedule::class, 'hr_work_schedule_id');
    }

    public function payrollPeriodicity()
    {
        return $this->belongsTo(\App\Models\PayrollPeriodicity::class, 'payroll_periodicity_id');
    }

    public function payrollEmployerRegistration()
    {
        return $this->belongsTo(\App\Models\PayrollEmployerRegistration::class, 'payroll_employer_registration_id');
    }
    /*
     * V5.64.2c-end
     */


    /*
     * V5.64.3b-start
     * Expediente documental del empleado.
     */
    public function documents()
    {
        return $this->hasMany(\App\Models\EmployeeDocument::class);
    }
    /*
     * V5.64.3b-end
     */


    /*
     * V5.64.4b-start
     * Incidencias del empleado.
     */
    public function incidents()
    {
        return $this->hasMany(\App\Models\EmployeeIncident::class);
    }
    /*
     * V5.64.4b-end
     */

    public function getAvatarUrlAttribute(): ?string
    {
        if (blank($this->avatar_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->avatar_path)) {
            return null;
        }

        return asset('storage/' . ltrim($this->avatar_path, '/'));
    }
    public function setPlainPosPinAttribute($value): void
    {
        $value = trim((string) $value);

        if ($value !== '') {
            $this->attributes['pos_pin_hash'] = Hash::make($value);
        }
    }

}
