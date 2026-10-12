<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEmployerRegistration extends Model
{
    protected $fillable = [
        'company_id',
        'employer_company_id',
        'name',
        'registration_number',
        'risk_class',
        'risk_fraction',
        'risk_premium_percent',
        'state',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'risk_premium_percent' => 'decimal:5',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Razon social que legalmente es propietaria del registro patronal.
     *
     * company_id se conserva como empresa administradora/tenant.
     */
    public function employerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'employer_company_id');
    }

    public function displayLabel(): string
    {
        $company = $this->employerCompany?->name ?: 'Patron sin asignar';
        $number = $this->registration_number ?: 'Sin registro';
        $name = $this->name ?: 'Registro patronal';

        return $company . ' | ' . $number . ' | ' . $name;
    }
}
