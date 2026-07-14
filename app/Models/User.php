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
        // BEXIA_V582_PERF7I_GROUP_ADMIN_REQUEST_CACHE
        static $bexiaPerf7iGroupAdminCache = [];

        $args = func_get_args();
        $userId = (int) $this->getKey();
        $cacheKey = $userId . '|' . md5(serialize($args));

        if (array_key_exists($cacheKey, $bexiaPerf7iGroupAdminCache)) {
            return $bexiaPerf7iGroupAdminCache[$cacheKey];
        }

        try {
            if ((bool) ($this->is_system_admin ?? false) || (method_exists($this, 'isSystemAdmin') && $this->isSystemAdmin())) {
                return $bexiaPerf7iGroupAdminCache[$cacheKey] = true;
            }

            $query = $this->companyGroups();

            if (isset($args[0]) && is_numeric($args[0])) {
                $query->where('company_groups.id', (int) $args[0]);
            }

            return $bexiaPerf7iGroupAdminCache[$cacheKey] = (bool) $query
                ->wherePivot('is_group_admin', true)
                ->exists();
        } catch (\Throwable $e) {
            report($e);

            return $bexiaPerf7iGroupAdminCache[$cacheKey] = false;
        }
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

        $path = $this->avatar_path;

        if (is_array($path)) {
            $path = collect($path)->filter()->first();
        }

        if (is_string($path)) {
            $decoded = json_decode($path, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $path = collect($decoded)->filter()->first();
            }
        }

        if (! filled($path)) {
            return null;
        }

        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'storage/')) {
            $publicPath = substr($path, strlen('storage/'));

            return Storage::disk('public')->exists($publicPath)
                ? asset($path)
                : null;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }
}
