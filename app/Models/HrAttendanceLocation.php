<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendanceLocation extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'address',
        'geofence_type',
        'latitude',
        'longitude',
        'radius_meters',
        'polygon_coordinates',
        'accuracy_required_meters',
        'allow_mobile_clock_in',
        'requires_review_when_outside',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_meters' => 'integer',
        'polygon_coordinates' => 'array',
        'accuracy_required_meters' => 'integer',
        'allow_mobile_clock_in' => 'boolean',
        'requires_review_when_outside' => 'boolean',
        'is_active' => 'boolean',
    ];


    public function usesPolygonGeofence(): bool
    {
        return ($this->geofence_type ?: 'circle') === 'polygon';
    }

    public function polygonPoints(): array
    {
        $points = $this->polygon_coordinates;

        if (is_string($points)) {
            $decoded = json_decode($points, true);
            $points = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($points)) {
            return [];
        }

        return collect($points)
            ->map(function ($point): ?array {
                if (! is_array($point) || count($point) < 2) {
                    return null;
                }

                return [
                    (float) $point[0],
                    (float) $point[1],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
    public function employees()
    {
        return $this->belongsToMany(
            \App\Models\Employee::class,
            'employee_attendance_location_assignments',
            'hr_attendance_location_id',
            'employee_id'
        )
            ->withPivot(['company_id', 'is_active', 'notes', 'created_by_user_id', 'updated_by_user_id'])
            ->withTimestamps();
    }


}
