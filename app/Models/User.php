<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants, HasAvatar
{
    use Notifiable;
    use HasRoles;

    protected string $guard_name = 'web';

    protected $fillable = [
        'default_location_id',
        'default_warehouse_id',
        'name',
        'email',
        'password',
        'avatar_path',
        'locale',
        'is_system_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_system_admin' => 'boolean',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Company::class);
    }

    public function companyGroups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\CompanyGroup::class)
            ->withPivot('is_group_admin')
            ->withTimestamps();
    }

    public function adminCompanyGroups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\CompanyGroup::class)
            ->withPivot('is_group_admin')
            ->wherePivot('is_group_admin', true);
    }

    public function isSystemAdmin(): bool
    {
        return (bool) $this->is_system_admin;
    }

    public function isGroupAdmin(): bool
    {
        return $this->adminCompanyGroups()->exists();
    }

    public function manageableCompanyGroupIds(): array
    {
        if ($this->isSystemAdmin()) {
            return \App\Models\CompanyGroup::query()->pluck('id')->all();
        }

        return $this->adminCompanyGroups()->pluck('company_groups.id')->all();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($this->isGroupAdmin()) {
            return true;
        }

        return $this->companies()->exists();
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSystemAdmin()) {
            return \App\Models\Company::query()->orderBy('name')->get();
        }

        $groupIds = $this->manageableCompanyGroupIds();

        return \App\Models\Company::query()
            ->where(function ($query) use ($groupIds) {
                $query->whereIn('companies.id', $this->companies()->pluck('companies.id'));

                if (! empty($groupIds)) {
                    $query->orWhereIn('company_group_id', $groupIds);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof \App\Models\Company) {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($this->companies()->whereKey($tenant->getKey())->exists()) {
            return true;
        }

        $groupIds = $this->manageableCompanyGroupIds();

        if (! empty($groupIds) && in_array((int) $tenant->company_group_id, $groupIds, true)) {
            return true;
        }

        return false;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (! filled($this->avatar_path)) {
            return null;
        }

        $path = ltrim((string) $this->avatar_path, '/');

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }
}
